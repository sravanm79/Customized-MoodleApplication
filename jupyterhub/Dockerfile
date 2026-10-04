FROM quay.io/jupyterhub/jupyterhub:5.5.2

# DockerSpawner: one container per student. Idle culler: stops servers nobody has used for an hour.
RUN pip install --no-cache-dir dockerspawner==14.0.0 jupyterhub-idle-culler==1.4.0

COPY jupyterhub_config.py /srv/jupyterhub/jupyterhub_config.py

RUN mkdir -p /srv/jupyterhub/data
WORKDIR /srv/jupyterhub

CMD ["jupyterhub", "-f", "/srv/jupyterhub/jupyterhub_config.py"]
