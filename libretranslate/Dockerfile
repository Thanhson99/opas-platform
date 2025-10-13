# Use the latest pinned image (includes CTranslate2 >= 3.21, supports model v6)
FROM libretranslate/libretranslate@sha256:29f1ebf92294f363e0441db4967a0ae7aaf4bad913edc9fcb1c59979cfe62e6f

# No need to run update-models since the online index often lacks "vi"
# RUN libretranslate --update-models || true

# Copy all model files (.argosmodel) into the image
COPY ./libretranslate_models/packages /home/libretranslate/.local/share/argos-translate/packages

USER root
RUN mkdir -p /home/libretranslate/.local/cache \
 && mkdir -p /home/libretranslate/.local/share/argos-translate \
 && chown -R libretranslate:libretranslate /home/libretranslate/.local
USER libretranslate
ENV HOME=/home/libretranslate

# Install all models during build (no runtime dependency)
RUN /app/venv/bin/python - <<'PY'
import glob
from argostranslate import package
for p in glob.glob('/home/libretranslate/.local/share/argos-translate/packages/*.argosmodel'):
    print("Installing", p)
    package.install_from_path(p)
print("Installed OK.")
PY

EXPOSE 5000
ENTRYPOINT []
CMD ["/app/venv/bin/libretranslate", "--host", "0.0.0.0", "--port", "5000", "--debug"]
