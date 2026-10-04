"""JupyterHub config for the Moodle mod_jupyter plugin.

Users never log in to the hub directly: Moodle creates them through the REST API (using the "moodle" service token)
and hands the browser a short-lived user token.

Each student gets their own container (DockerSpawner) running as an unprivileged user, with their own volume, CPU and
memory limits, on a network that cannot reach the database. Browsers reach the hub through Moodle's Apache at
https://<moodle>/jupyter/ (same origin as Moodle, same certificate); Moodle itself calls http://jupyterhub:8000/jupyter/.
"""
import os
import sys

c = get_config()  # noqa: F821
moodle_origin = os.environ.get("MOODLE_ORIGIN", "https://192.168.30.239")

c.JupyterHub.base_url = "/jupyter/"
c.JupyterHub.bind_url = "http://:8000/jupyter/"
# The hub API must be reachable from the students' containers.
c.JupyterHub.hub_ip = "0.0.0.0"
c.JupyterHub.hub_connect_ip = "moodle_jupyterhub"
c.JupyterHub.db_url = "sqlite:////srv/jupyterhub/data/jupyterhub.sqlite"
c.JupyterHub.cookie_secret_file = "/srv/jupyterhub/data/jupyterhub_cookie_secret"
c.JupyterHub.authenticator_class = "null"
c.JupyterHub.tornado_settings = {
    "headers": {"Content-Security-Policy": f"frame-ancestors 'self' {moodle_origin}"},
}

c.JupyterHub.spawner_class = "docker"
c.DockerSpawner.image = os.environ.get("SINGLEUSER_IMAGE", "moodle-jupyter-singleuser:latest")
c.DockerSpawner.network_name = os.environ.get("DOCKER_NETWORK_NAME", "moodle_jupyter")
c.DockerSpawner.use_internal_ip = True
c.DockerSpawner.name_template = "jupyter-{username}"
c.DockerSpawner.notebook_dir = "/home/jovyan/work"
# One volume per student; it outlives the container, which is removed when the server stops.
c.DockerSpawner.volumes = {"jupyter-{username}": "/home/jovyan/work"}
c.DockerSpawner.remove = True
c.DockerSpawner.mem_limit = os.environ.get("SINGLEUSER_MEM_LIMIT", "1G")
c.DockerSpawner.cpu_limit = float(os.environ.get("SINGLEUSER_CPU_LIMIT", "1"))
c.DockerSpawner.extra_host_config = {
    "pids_limit": 512,
    "security_opt": ["no-new-privileges"],
    "cap_drop": ["ALL"],
}
c.Spawner.default_url = "/lab"
c.Spawner.start_timeout = 120
c.Spawner.http_timeout = 60
c.Spawner.environment = {
    "MOODLE_ORIGIN": moodle_origin,
    "JUPYTERHUB_ALLOW_TOKEN_IN_URL": "1",
}

c.JupyterHub.services = [
    {"name": "moodle", "api_token": os.environ["MOODLE_SERVICE_TOKEN"]},
    {
        "name": "idle-culler",
        "command": [sys.executable, "-m", "jupyterhub_idle_culler",
                    "--timeout=" + os.environ.get("IDLE_TIMEOUT_SECONDS", "3600")],
    },
]
c.JupyterHub.load_roles = [
    {
        "name": "moodle-service",
        "scopes": ["admin:users", "admin:servers", "access:servers", "tokens"],
        "services": ["moodle"],
    },
    {
        "name": "idle-culler",
        "scopes": ["list:users", "read:users:activity", "read:servers", "delete:servers"],
        "services": ["idle-culler"],
    },
]
