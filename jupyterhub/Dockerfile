FROM quay.io/jupyterhub/jupyterhub:5

# JupyterLab for the single-user servers, plus common libraries for assignments.
RUN pip install --no-cache-dir jupyterlab numpy pandas matplotlib

COPY jupyterhub_config.py /srv/jupyterhub/jupyterhub_config.py
COPY jupyter_server_config.py /etc/jupyter/jupyter_server_config.py

RUN mkdir -p /srv/jupyterhub/data /srv/jupyterhub/home
WORKDIR /srv/jupyterhub

CMD ["jupyterhub", "-f", "/srv/jupyterhub/jupyterhub_config.py"]
