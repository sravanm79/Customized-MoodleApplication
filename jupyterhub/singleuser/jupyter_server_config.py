"""Config for each student's Jupyter server: allow being shown inside Moodle (same origin, behind /jupyter/)."""
import os

c = get_config()  # noqa: F821
moodle_origin = os.environ.get("MOODLE_ORIGIN", "https://192.168.30.239")
c.ServerApp.tornado_settings = {
    "headers": {"Content-Security-Policy": f"frame-ancestors 'self' {moodle_origin}"},
}
