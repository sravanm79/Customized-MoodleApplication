# Image for each student's own Jupyter server (started by the hub's DockerSpawner).
# Runs as the unprivileged user jovyan (uid 1000) without sudo; work is kept in /home/jovyan/work (one volume per student).
FROM quay.io/jupyter/base-notebook:2025-09-29

# jupyterhub must match the hub's version (5.5.2) for the single-user server to talk to it.
RUN pip install --no-cache-dir "jupyterhub==5.5.2" numpy pandas matplotlib

COPY jupyter_server_config.py /etc/jupyter/jupyter_server_config.py
