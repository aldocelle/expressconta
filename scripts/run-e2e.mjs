import {spawnSync} from 'node:child_process';
const result=spawnSync(process.execPath,['node_modules/@playwright/test/cli.js','test',...process.argv.slice(2)],{stdio:'inherit',env:{...process.env,PW_DISABLE_TS_ESM:'1'}});
if(result.error)throw result.error;
process.exit(result.status??1);
