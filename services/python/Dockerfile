FROM python:3.11-slim-bookworm

WORKDIR /app

# Update system packages to patch vulnerabilities
RUN apt-get update && apt-get upgrade -y && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY . .

# Install shared runtime dependencies first, then service-specific requirements.
RUN pip install --no-cache-dir fastapi uvicorn requests pydantic-settings \
    && find /app -name requirements.txt -type f -print0 \
    | while IFS= read -r -d '' requirements_file; do \
        pip install --no-cache-dir -r "$requirements_file"; \
    done

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "5000"]
