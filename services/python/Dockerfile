FROM python:3.11-slim-bookworm

WORKDIR /app

# Update system packages to patch vulnerabilities
RUN apt-get update && apt-get upgrade -y && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY . .

# Install Python dependencies from every service requirements file.
RUN find /app -name requirements.txt -type f -print0 | xargs -0 -I{} sh -c 'pip install --no-cache-dir -r "$1"' -- {} \
    && pip install --no-cache-dir fastapi uvicorn

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "5000"]
