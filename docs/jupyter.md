# Jupyter notebooks (mod_jupyter + JupyterHub)

Each student gets **their own container** (JupyterHub with DockerSpawner):

| | |
| --- | --- |
| Address | `https://192.168.30.239/jupyter/`, through Moodle's Apache (same address and certificate as Moodle; plain http is refused). Moodle's server talks to `http://jupyterhub:8000/jupyter`. |
| Student container | `jupyter-moodle<userid>`, image `moodle-jupyter-singleuser` (JupyterLab, numpy, pandas, matplotlib), runs as the unprivileged user `jovyan` (uid 1000), no sudo, `no-new-privileges`, all Linux capabilities dropped. |
| Storage | Docker volume `jupyter-moodle<userid>` mounted at `/home/jovyan/work`; it survives the container. A student only ever sees their own volume. |
| Limits | 1 CPU core, 1 GB RAM, 512 processes per student (`SINGLEUSER_CPU_LIMIT`, `SINGLEUSER_MEM_LIMIT` in `jupyterhub_config.py` / environment). |
| Network | Students' containers are on the `moodle_jupyter` network: they reach the hub and the internet (`pip install`), **not** the database, Moodle's container or other students' servers by name. |
| Idle shutdown | `jupyterhub-idle-culler` stops a server after 1 hour without activity (`IDLE_TIMEOUT_SECONDS`); work stays in the volume and the server restarts on the next visit. |

The hub needs `/var/run/docker.sock` to start containers. That makes the hub container as powerful as Docker on
this server, so it must not run anything else and its port is not published.

## Build / update the student image

```bash
docker compose --profile build-only build jupyter-singleuser   # after editing jupyterhub/singleuser/
docker compose up -d --build jupyterhub                         # after editing jupyterhub/jupyterhub_config.py
```

Running servers keep the old image until they are stopped (idle culler, or `docker stop jupyter-moodle<id>`).

## Migration (2026-10-04)

Before, all students' servers ran as root in one container, with home folders readable by everyone. Their files
were copied into the per-student volumes (`jupyter-moodle2`, `-9010`, `-9014`, `-9015`). The old volume
`moodle_jupyterhub_home` is kept, untouched, as a backup; delete it with `docker volume rm moodle_jupyterhub_home`
once you are satisfied.
