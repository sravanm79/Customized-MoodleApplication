"""Moodle capacity test: staged ramp, three separate tracks.

  TRACK=browse   Normal students: dashboard, course, page, forum, multiple-choice quiz (DB/PHP bound)
  TRACK=coding   CodeRunner quiz: answers are run in the Jobe sandbox on submit
  TRACK=jupyter  mod_jupyter: each user gets a Jupyter server + kernel that runs code

Env: TRACK, STAGES="25,50,100", STAGE_SECONDS=180, SPAWN_RATE=10, OUT=results/<run>, MEM_GUARD_GB=6
"""
import csv
import json
import os
import random
import re
import time
import uuid
from itertools import count

import websocket
from locust import HttpUser, LoadTestShape, between, events, task

HERE = os.path.dirname(os.path.abspath(__file__))
COURSE = json.load(open(os.path.join(HERE, "setup", "course.json")))
TRACK = os.environ.get("TRACK", "browse")
STAGES = [int(s) for s in os.environ.get("STAGES", "25,50,100").split(",")]
STAGE_SECONDS = int(os.environ.get("STAGE_SECONDS", "180"))
SPAWN_RATE = float(os.environ.get("SPAWN_RATE", "10"))
OUT = os.environ.get("OUT", os.path.join(HERE, "results", TRACK))
MEM_GUARD_GB = float(os.environ.get("MEM_GUARD_GB", "6"))
os.makedirs(OUT, exist_ok=True)

_user_numbers = count(1)
_current_stage = {"users": 0, "index": -1}

# ---------- raw per-request log (used for per-stage SLA analysis) ----------
# Opened lazily: Locust forks its worker processes after importing this file, so each process
# must open its own requests-<pid>.csv.
_req = {"pid": None, "file": None, "csv": None}


def _req_writer():
    if _req["pid"] != os.getpid():
        f = open(os.path.join(OUT, f"requests-{os.getpid()}.csv"), "w", newline="")
        w = csv.writer(f)
        w.writerow(["ts", "stage_users", "name", "ms", "ok", "error"])
        _req.update(pid=os.getpid(), file=f, csv=w)
    return _req["csv"]


@events.request.add_listener
def _log_request(request_type, name, response_time, response_length, exception, **kw):
    _req_writer().writerow([f"{time.time():.3f}", _current_stage["users"], name, f"{response_time:.1f}",
                            0 if exception else 1, str(exception)[:120] if exception else ""])


@events.quitting.add_listener
def _close(environment, **kw):
    if _req["file"] and _req["pid"] == os.getpid():
        _req["file"].close()


# ---------- staged ramp with host memory guard ----------
def _mem_available_gb():
    with open("/proc/meminfo") as f:
        for line in f:
            if line.startswith("MemAvailable:"):
                return int(line.split()[1]) / 1048576
    return 99.0


class StagedRamp(LoadTestShape):
    """Hold each concurrency level for STAGE_SECONDS; the highest stage that meets the SLA is the answer."""

    def tick(self):
        t = self.get_run_time()
        idx = int(t // STAGE_SECONDS)
        if idx >= len(STAGES):
            return None
        if _mem_available_gb() < MEM_GUARD_GB:
            with open(os.path.join(OUT, "ABORTED.txt"), "w") as f:
                f.write(f"Stopped at stage {STAGES[idx]} users: host MemAvailable below {MEM_GUARD_GB} GB\n")
            return None
        if idx != _current_stage["index"]:
            _current_stage.update(index=idx, users=STAGES[idx])
            with open(os.path.join(OUT, "stages.csv"), "a") as f:
                f.write(f"{time.time():.3f},{STAGES[idx]}\n")
        return STAGES[idx], SPAWN_RATE


# ---------- helpers ----------
SESSKEY_RE = re.compile(r'"sesskey":"([^"]+)"')
LOGINTOKEN_RE = re.compile(r'name="logintoken" value="([^"]+)"')
FORM_RE = re.compile(r'<form[^>]*id="responseform".*?</form>', re.S)
HIDDEN_RE = re.compile(r'type="hidden" name="([^"]+)" value="([^"]*)"')
RADIO_RE = re.compile(r'type="radio" name="([^"]+)" value="([^"]+)"')
TEXTAREA_RE = re.compile(r'<textarea[^>]*name="([^"]+)"')


class MoodleUser(HttpUser):
    abstract = True
    host = "http://localhost:9999"

    def on_start(self):
        # Unique account per virtual user, also across Locust worker processes (--processes N).
        runner = self.environment.runner
        index = getattr(runner, "worker_index", 0) or 0
        procs = int(os.environ.get("PROCS", "1"))
        n = next(_user_numbers) * procs - (procs - 1) + index
        n = (n - 1) % int(COURSE["users"]) + 1  # replacement users (after failures) wrap around
        self.username = f"{COURSE['prefix']}{n:04d}"
        self.sesskey = None
        self.login()

    def get(self, url, name, expect=None):
        self._ensure_login()
        with self.client.get(url, name=name, catch_response=True) as r:
            self._check(r, expect)
            return r

    def post(self, url, data, name, expect=None):
        self._ensure_login()
        with self.client.post(url, data=data, name=name, catch_response=True) as r:
            self._check(r, expect)
            return r

    def _check(self, r, expect):
        if r.status_code >= 400:
            r.failure(f"HTTP {r.status_code}")
        elif "/login/index.php" in r.url:
            r.failure("bounced to login page (session lost)")
            self.logged_in = False
        elif 'class="errorbox' in r.text or "moodle-exception" in r.text[:5000]:
            r.failure("Moodle error page")
        elif expect and expect not in r.text:
            r.failure(f"missing {expect!r}")
        else:
            m = SESSKEY_RE.search(r.text)
            if m:
                self.sesskey = m.group(1)

    def _ensure_login(self):
        # A student whose session was lost (or whose login failed) logs in again before the next page.
        if not getattr(self, "logged_in", False):
            self.login()

    def login(self):
        self.logged_in = True  # set first so the login requests themselves don't recurse
        self.client.cookies.clear()
        r = self.client.get("/login/index.php", name="login: form")
        m = LOGINTOKEN_RE.search(r.text or "")
        if not m:
            self.logged_in = False
            return
        tok = m.group(1)
        with self.client.post("/login/index.php", name="login: submit", catch_response=True,
                              data={"username": self.username, "password": COURSE["password"], "logintoken": tok}) as r:
            if "/my/" not in r.url:
                r.failure(f"login failed for {self.username}")
                self.logged_in = False
            else:
                m = SESSKEY_RE.search(r.text)
                self.sesskey = m.group(1) if m else None

    def quiz_attempt(self, cmid, label, answer_code=None):
        self.get(f"/mod/quiz/view.php?id={cmid}", f"{label}: view")
        r = self.post("/mod/quiz/startattempt.php", {"cmid": cmid, "sesskey": self.sesskey}, f"{label}: start attempt",
                      expect='id="responseform"')
        m = FORM_RE.search(r.text or "")
        if not m:
            return
        form = m.group(0)
        data = dict(HIDDEN_RE.findall(form))
        for name in {n for n, _ in RADIO_RE.findall(form)}:
            data[name] = str(random.randint(0, 3))
        for name in TEXTAREA_RE.findall(form):
            data[name] = answer_code or ""
        data.update(finishattempt="1", timeup="0")
        if answer_code:
            time.sleep(random.uniform(20, 40))  # student writing code
        else:
            time.sleep(random.uniform(10, 20))  # student reading questions
        self.post("/mod/quiz/processattempt.php", data, f"{label}: submit & grade", expect="review")


# ---------- Track 1: normal browsing + multiple-choice quiz ----------
class BrowseStudent(MoodleUser):
    wait_time = between(5, 15)

    @task(2)
    def dashboard(self):
        self.get("/my/", "page: dashboard")

    @task(3)
    def course(self):
        self.get(f"/course/view.php?id={COURSE['courseid']}", "page: course")

    @task(3)
    def reading_page(self):
        self.get(f"/mod/page/view.php?id={COURSE['page']}", "page: reading page")

    @task(1)
    def forum(self):
        self.get(f"/mod/forum/view.php?id={COURSE['forum']}", "page: forum")
        self.get(f"/mod/forum/discuss.php?d={COURSE['discussion']}", "page: forum thread")

    @task(1)
    def mcq_quiz(self):
        self.quiz_attempt(COURSE["mcq"], "mcq quiz")


# ---------- Track 2: CodeRunner (Jobe sandbox) ----------
SOLUTIONS = [
    "def square(n):\n    return n * n\n",
    "def square(n):\n    return n ** 2\n",
    "def square(n):\n    total = 0\n    for _ in range(abs(n)):\n        total += abs(n)\n    return total\n",
    "def square(n):\n    return n + n\n",  # wrong answer, still executed
]


class CodingStudent(MoodleUser):
    wait_time = between(10, 30)

    @task
    def coding_quiz(self):
        self.get(f"/course/view.php?id={COURSE['courseid']}", "page: course")
        self.quiz_attempt(COURSE["code"], "coding quiz", answer_code=random.choice(SOLUTIONS))


# ---------- Track 3: Jupyter per-user server + kernel ----------
CELLS = [
    "import numpy as np, pandas as pd\ndf = pd.DataFrame({'a': np.random.randint(0, 100, 100000), 'b': np.random.rand(100000)})\nprint(df.groupby('a').b.mean().head())",
    "s = 'Python'\nprint(s[1:4], len(s), s.upper())",
    "print(sum(i * i for i in range(200000)))",
    "words = 'the cat and the dog and the bird'.split()\nprint({w: words.count(w) for w in set(words)})",
]


class JupyterStudent(MoodleUser):
    wait_time = between(15, 30)

    def on_start(self):
        super().on_start()
        self.kernel = None
        r = self.get(f"/mod/jupyter/view.php?id={COURSE['jupyter']}", "jupyter: open activity (spawn server)",
                     expect="<iframe")
        m = re.search(r'<iframe[^>]*src="([^"]+)"', r.text or "")
        if not m:
            return
        src = m.group(1).replace("&amp;", "&")
        self.hub = src.split("/user/")[0]
        self.userpath = "/user/" + src.split("/user/")[1].split("/")[0]
        self.token = src.split("token=")[1]
        self.auth = {"Authorization": f"token {self.token}"}
        self.client.get(src, name="jupyter: load JupyterLab")
        r = self.client.post(f"{self.hub}{self.userpath}/api/kernels", json={"name": "python3"}, headers=self.auth,
                             name="jupyter: start kernel")
        if r.ok:
            self.kernel = r.json()["id"]
            self.run_cell(CELLS[0])  # first cell imports pandas, like real notebooks

    def run_cell(self, code):
        if not self.kernel:
            return
        url = f"{self.hub.replace('http', 'ws')}{self.userpath}/api/kernels/{self.kernel}/channels?token={self.token}"
        start = time.time()
        exc = None
        try:
            ws = websocket.create_connection(url, timeout=120)
            msg_id = uuid.uuid4().hex
            ws.send(json.dumps({
                "header": {"msg_id": msg_id, "username": self.username, "session": uuid.uuid4().hex,
                           "msg_type": "execute_request", "version": "5.3"},
                "parent_header": {}, "metadata": {}, "channel": "shell",
                "content": {"code": code, "silent": False, "store_history": True, "user_expressions": {},
                            "allow_stdin": False, "stop_on_error": True}}))
            while True:
                m = json.loads(ws.recv())
                if m.get("parent_header", {}).get("msg_id") == msg_id and m["msg_type"] == "execute_reply":
                    if m["content"]["status"] != "ok":
                        exc = RuntimeError(m["content"].get("ename", "error"))
                    break
            ws.close()
        except Exception as e:  # noqa: BLE001
            exc = e
        events.request.fire(request_type="WS", name="jupyter: run cell", response_time=(time.time() - start) * 1000,
                            response_length=0, exception=exc, context={})

    @task(4)
    def run_code(self):
        self.run_cell(random.choice(CELLS[1:]))

    @task(1)
    def rerun_pandas(self):
        self.run_cell(CELLS[0])

    def on_stop(self):
        if getattr(self, "kernel", None):
            self.client.delete(f"{self.hub}{self.userpath}/api/kernels/{self.kernel}", headers=self.auth,
                               name="jupyter: stop kernel")


_classes = {"browse": BrowseStudent, "coding": CodingStudent, "jupyter": JupyterStudent}
for _name, _cls in _classes.items():
    if _name != TRACK:
        _cls.abstract = True
del _name, _cls  # Locust collects every User class at module level.
