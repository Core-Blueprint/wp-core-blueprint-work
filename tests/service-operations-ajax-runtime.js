'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname,'../assets/service-operations.js'),'utf8');
let submit;
const badge={textContent:'Not recorded'};
const feedback={textContent:''};
const button={disabled:false};
const form={
    elements:{revision:{value:'0'}},
    querySelector(selector){return selector==='button[type="submit"]'?button:feedback;},
    closest(){return {querySelector(){return badge;}};}
};
const context={
    window:{cbWorkServiceOperations:{
        ajaxUrl:'/wp-admin/admin-ajax.php',saved:'Saved.',error:'Error',invoiced:'Invoiced externally',notRecorded:'Not recorded'
    },fetch:true},
    document:{addEventListener(name,fn){assert.equal(name,'submit');submit=fn;}},
    FormData:class{constructor(){this.map=new Map([['revision','0']]);}set(k,v){this.map.set(k,v);}get(k){return this.map.get(k);}},
    fetch:async (_url,options)=>{
        assert.equal(options.method,'POST');
        assert.equal(options.body.get('action'),'cb_work_service_invoice_ajax');
        return {ok:true,json:async()=>({success:true,data:{registration:{revision:1,state:'invoiced'}}})};
    }
};
vm.runInNewContext(source,context);
(async()=>{
    let prevented=false;
    await submit({target:{closest(){return form;}},preventDefault(){prevented=true;}});
    assert.equal(prevented,true);
    assert.equal(form.elements.revision.value,'1');
    assert.equal(badge.textContent,'Invoiced externally');
    assert.equal(feedback.textContent,'Saved.');
    assert.equal(button.disabled,false);
    context.fetch=async()=>({ok:false,json:async()=>({success:false,data:{code:'work_service_registration_stale'}})});
    await submit({target:{closest(){return form;}},preventDefault(){}});
    assert.match(feedback.textContent,/work_service_registration_stale/);
    assert.equal(button.disabled,false);
    console.log('Service Operations AJAX runtime: PASS');
})().catch(e=>{console.error(e);process.exitCode=1;});
