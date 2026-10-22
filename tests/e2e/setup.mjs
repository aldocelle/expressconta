import {writeFileSync} from 'node:fs';
import {execFileSync} from 'node:child_process';
import {e2eEnv} from './environment.mjs';
export default function setup(){writeFileSync(e2eEnv.DB_DATABASE,'');for(const args of [['artisan','migrate','--force'],['artisan','db:seed','--class=DemoSeeder']])execFileSync('php',args,{env:e2eEnv,stdio:'pipe'});}
