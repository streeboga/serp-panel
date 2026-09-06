# Официальный образ Playwright: Chromium и его системные зависимости уже внутри,
# собирать их руками на проде — лишний способ поломать сервер.
# Версия образа и версия playwright-core обязаны совпадать: Chromium лежит внутри
# образа, а playwright-core ищет строго ту сборку, под которую собран. Разъезд даёт
# «Executable doesn't exist» уже в бою.
FROM mcr.microsoft.com/playwright:v1.49.0-jammy

WORKDIR /app

COPY package.json ./
RUN npm install --omit=dev --no-audit --no-fund

COPY server.js in-page.js lighthouse.js math-check.mjs ./

ENV PORT=8081
EXPOSE 8081

USER pwuser

CMD ["node", "server.js"]
