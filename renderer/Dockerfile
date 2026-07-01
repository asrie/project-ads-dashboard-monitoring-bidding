# syntax=docker/dockerfile:1
# Playwright base image ships Chromium + all OS deps preinstalled.
FROM mcr.microsoft.com/playwright:v1.49.1-jammy

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm install --omit=dev --no-audit --no-fund

COPY server.js ./

ENV NODE_ENV=production
ENV PORT=3000
EXPOSE 3000

# Run as the non-root user provided by the Playwright image.
USER pwuser

CMD ["node", "server.js"]
