"""Config for each user's Jupyter server (spawned by the hub)."""
import os

c = get_config()  # noqa: F821

# The hub runs as root inside the container, so user servers do too.
c.ServerApp.allow_root = True

# Allow JupyterLab to be embedded in Moodle.
moodle_origin = os.environ.get("MOODLE_ORIGIN", "http://localhost:9999")
c.ServerApp.tornado_settings = {
    "headers": {"Content-Security-Policy": f"frame-ancestors 'self' {moodle_origin}"},
}
