// k6 load test: each virtual user (VU) is one real student account loadtestNNNN.
// Flow: login once -> loop { dashboard, course, assignment, (submit notebook once if UPLOAD=1), grades } with think time.
// Env: BASE, STAGES (e.g. "100:2m,100:3m,250:2m"), THINK_MIN/THINK_MAX (s), UPLOAD (0/1), SUBMIT_ONLY (1 = login+submit then idle)
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend, Counter } from 'k6/metrics';

const BASE = __ENV.BASE || 'http://localhost:9999';
const CMID = __ENV.CMID || '3';
const COURSE = __ENV.COURSE || '2';
const THINK_MIN = +(__ENV.THINK_MIN || 5), THINK_MAX = +(__ENV.THINK_MAX || 15);
const UPLOAD = __ENV.UPLOAD === '1', SUBMIT_ONLY = __ENV.SUBMIT_ONLY === '1';
const NB = open('./reference_submission.ipynb');

const tLogin = new Trend('t_login', true), tPage = new Trend('t_page', true);
const tAjax = new Trend('t_ajax', true);
const tUpload = new Trend('t_upload', true), tSubmit = new Trend('t_submit', true);
const failures = new Counter('moodle_failures'), submissions = new Counter('submissions_ok');

function stages() {
  return (__ENV.STAGES || '10:30s,10:1m').split(',').map(s => { const [t, d] = s.split(':'); return { target: +t, duration: d }; });
}
export const options = {
  noCookiesReset: true,
  scenarios: { students: { executor: 'ramping-vus', startVUs: 0, stages: stages(), gracefulRampDown: '30s', gracefulStop: '60s' } },
  thresholds: { http_req_failed: ['rate<0.01'], t_page: ['p(95)<3000'] },
  summaryTrendStats: ['avg', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
};

let loggedIn = false, submitted = false;
const think = () => sleep(THINK_MIN + Math.random() * (THINK_MAX - THINK_MIN));
const field = (body, re) => { const m = body && body.match(re); return m ? m[1] : null; };

function page(url, name) {
  const r = http.get(BASE + url, { tags: { name }, timeout: '60s' });
  tPage.add(r.timings.duration);
  // Moodle returns 200 with an error box for many failures, so check the body too.
  if (!check(r, { [`${name} ok`]: x => x.status === 200 && !x.body.includes('errorcode') && !x.body.includes('id="login"') })) failures.add(1, { step: name });
  // A real browser also fires the navbar AJAX polls on every page load.
  const sk = field(r.body, /"sesskey":"([^"]+)"/), uid = field(r.body, /data-userid="(\d+)"/) || field(r.body, /"userid":(\d+)/);
  if (sk && uid) {
    const a = http.post(`${BASE}/lib/ajax/service.php?sesskey=${sk}`, JSON.stringify([
      { index: 0, methodname: 'core_message_get_unread_conversation_counts', args: { userid: +uid } },
      { index: 1, methodname: 'message_popup_get_popup_notifications', args: { limit: 20, offset: 0, useridto: +uid } },
    ]), { headers: { 'Content-Type': 'application/json' }, tags: { name: 'ajax_poll' }, timeout: '60s' });
    tAjax.add(a.timings.duration);
    if (!check(a, { 'ajax ok': x => x.status === 200 && !x.body.includes('"error":true') })) failures.add(1, { step: 'ajax' });
  }
  return r;
}

function login(user) {
  const t0 = Date.now();
  const lp = http.get(`${BASE}/login/index.php`, { tags: { name: 'login_page' }, timeout: '60s' });
  const token = field(lp.body, /name="logintoken" value="([^"]+)"/);
  const r = http.post(`${BASE}/login/index.php`, { username: user, password: 'LoadTest#2026', logintoken: token || '', anchor: '' },
    { tags: { name: 'login_post' }, timeout: '60s' });
  tLogin.add(Date.now() - t0);
  const ok = r.status === 200 && r.url.indexOf('/login/') === -1;
  if (!check(r, { 'login ok': () => ok })) failures.add(1, { step: 'login' });
  return ok;
}

function submit(user) {
  const r = page(`/mod/assign/view.php?id=${CMID}&action=editsubmission`, 'edit_submission');
  const sk = field(r.body, /name="sesskey" type="hidden" value="([^"]+)"/);
  const item = field(r.body, /value="(\d+)" name="files_filemanager"/);
  const lm = field(r.body, /name="lastmodified" type="hidden" value="(\d+)"/);
  const uid = field(r.body, /name="userid" type="hidden" value="(\d+)"/);
  const ctx = field(r.body, /"contextid":(\d+)/);
  const cid = field(r.body, /"client_id":"([^"]+)"/);
  if (!sk || !item) { failures.add(1, { step: 'edit_form' }); return; }
  // Make every student's notebook unique (real submissions differ), otherwise Moodle's
  // content-hash dedup would store 1000 identical uploads as a single file.
  const nb = JSON.parse(NB);
  nb.metadata.submission = { student: user, nonce: `${Date.now()}-${Math.random()}` };
  const body = JSON.stringify(nb, null, 1);
  let t0 = Date.now();
  const up = http.post(`${BASE}/repository/repository_ajax.php?action=upload`, {
    repo_upload_file: http.file(body, `${user}.ipynb`, 'application/x-ipynb+json'),
    sesskey: sk, repo_id: '5', itemid: item, ctx_id: ctx, savepath: '/', title: `${user}.ipynb`,
    author: user, license: 'allrightsreserved', client_id: cid,
  }, { tags: { name: 'upload' }, timeout: '120s' });
  tUpload.add(Date.now() - t0);
  if (!check(up, { 'upload ok': x => x.status === 200 && x.body.includes('draftfile.php') })) { failures.add(1, { step: 'upload' }); return; }
  t0 = Date.now();
  const s = http.post(`${BASE}/mod/assign/view.php`, {
    lastmodified: lm, id: CMID, userid: uid, action: 'savesubmission', sesskey: sk,
    _qf__mod_assign_submission_form: '1', files_filemanager: item, submitbutton: 'Save changes',
  }, { tags: { name: 'save_submission' }, timeout: '120s' });
  tSubmit.add(Date.now() - t0);
  if (check(s, { 'submit ok': x => x.status === 200 && x.url.includes('action=view') && !x.body.includes('errorcode') })) { submissions.add(1); submitted = true; }
  else failures.add(1, { step: 'submit' });
}

export default function () {
  const user = `loadtest${String(__VU).padStart(4, '0')}`;
  if (!loggedIn) { loggedIn = login(user); if (!loggedIn) { think(); return; } think(); }
  if (SUBMIT_ONLY) {
    if (!submitted) { page(`/mod/assign/view.php?id=${CMID}`, 'assign_view'); submit(user); }
    think(); return;
  }
  page('/my/', 'dashboard'); think();
  page(`/course/view.php?id=${COURSE}`, 'course_view'); think();
  page(`/mod/assign/view.php?id=${CMID}`, 'assign_view'); think();
  if (UPLOAD && !submitted) { submit(user); think(); }
  page(`/grade/report/overview/index.php?id=${COURSE}`, 'grades'); think();
}
