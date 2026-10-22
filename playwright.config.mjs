import {defineConfig} from '@playwright/test';
// @ts-expect-error JS exports environment variables used by PHP and test fixtures.
import {e2eEnv} from './tests/e2e/environment.mjs';
export default defineConfig({testDir:'./tests/e2e',globalSetup:'./tests/e2e/setup.mjs',workers:1,fullyParallel:false,timeout:30000,use:{baseURL:'http://127.0.0.1:8000',headless:true,trace:'retain-on-failure',screenshot:'only-on-failure',launchOptions:{args:['--no-sandbox']}},webServer:{command:'php artisan serve --host=127.0.0.1 --port=8000 --no-reload',url:'http://127.0.0.1:8000/up',reuseExistingServer:false,env:e2eEnv},reporter:'list'});
