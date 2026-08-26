# Официальный образ Playwright: Chromium и его системные зависимости уже внутри,
# собирать их руками на проде — лишний способ поломать сервер.
FROM mcr.microsoft.com/playwright:v1.49.0-jammy

WORKDIR /app

COPY package.json ./
RUN npm install --omit=dev --no-audit --no-fund

COPY server.js in-page.js ./

ENV PORT=8081
EXPOSE 8081

USER pwuser

CMD ["node", "server.js"]
