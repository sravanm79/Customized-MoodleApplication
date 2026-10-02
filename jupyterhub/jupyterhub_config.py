"""JupyterHub config for the Moodle mod_jupyter plugin.

Users never log in to the hub directly: Moodle creates them through the REST API
(using the "moodle" service token) and hands the browser a short-lived user token.
"""
import os

c = get_config()  # noqa: F821

moodle_origin = os.environ.get("MOODLE_ORIGIN", "http://localhost:9999")

c.JupyterHub.bind_url = "http://:8000"
c.JupyterHub.db_url = "sqlite:////srv/jupyterhub/data/jupyterhub.sqlite"
c.JupyterHub.cookie_secret_file = "/srv/jupyterhub/data/jupyterhub_cookie_secret"

# No login page: access is only via tokens issued to Moodle.
c.JupyterHub.authenticator_class = "null"

# One process per user inside this container, each with its own home directory.
c.JupyterHub.spawner_class = "simple"
c.SimpleLocalProcessSpawner.home_dir_template = "/srv/jupyterhub/home/{username}"
c.Spawner.default_url = "/lab"
c.Spawner.start_timeout = 120
c.Spawner.http_timeout = 60
c.Spawner.environment = {
    "MOODLE_ORIGIN": moodle_origin,
    "JUPYTERHUB_ALLOW_TOKEN_IN_URL": "1",
}

# Allow Moodle to show hub pages in an iframe.
c.JupyterHub.tornado_settings = {
    "headers": {"Content-Security-Policy": f"frame-ancestors 'self' {moodle_origin}"},
}

c.JupyterHub.services = [
    {"name": "moodle", "api_token": os.environ["MOODLE_SERVICE_TOKEN"]},
]
c.JupyterHub.load_roles = [
    {
        "name": "moodle-service",
        "scopes": ["admin:users", "admin:servers", "access:servers", "tokens"],
        "services": ["moodle"],
    },
]
