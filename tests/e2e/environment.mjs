import path from 'node:path';
export const e2eEnv={...process.env,APP_ENV:'local',APP_DEBUG:'false',APP_URL:'http://127.0.0.1:8000',APP_KEY:'base64:QnJ3eUduWVdLNVlpNHlENENZVVZwY1lQaVZkU3JmSWY=',DB_CONNECTION:'sqlite',DB_DATABASE:path.resolve('database/e2e.sqlite'),DEMO_PASSWORD:'ExpressConta-e2e-only',CACHE_STORE:'array',SESSION_DRIVER:'file',MAIL_MAILER:'log'};
