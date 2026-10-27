'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname,'../assets/service-operations.js'),'utf8');
let submit;
let click;
let change;
let detailsFocus=false;
let contextSelected='internal';
const checkbox={disabled:false};
const billingFields={
    hidden:false,
    querySelector(sel){assert.equal(sel,'[name="billing_enabled"]');return checkbox;}
};
const ruleOptions=[
    {value:'0',selected:true,dataset:{}},
    {value:'17',selected:false,dataset:{project:'123',service:'9',context:'internal'},disabled:false,hidden:false},
    {value:'18',selected:false,dataset:{project:'123',service:'9',context:'customer'},disabled:false,hidden:false},
    {value:'19',selected:false,dataset:{project:'999',service:'9',context:'internal'},disabled:false,hidden:false}
];
const rulePicker={value:'0',options:ruleOptions};
const serviceSelector={value:'9',closest(sel){assert.equal(sel,'form');return projectForm;}};
const projectForm={
    querySelector(sel){
        const nodes={
            '[data-cb-so-billing-fields]':billingFields,
            '[data-cb-so-project-context]':projectSelector,
            '[data-cb-so-service-id]':serviceSelector,
            '[data-cb-so-rule-picker]':rulePicker
        };
        assert.ok(Object.hasOwn(nodes,sel));
        return nodes[sel];
    }
};
const projectSelector={
    value:'123',
    closest(sel){assert.equal(sel,'form');return projectForm;},
    get selectedOptions(){return [{dataset:{context:contextSelected}}];}
};
let detailsScroll=false;
const details={
    open:false,
    scrollIntoView(){detailsScroll=true;},
    querySelector(selector){
        assert.equal(selector,'select[name="project_id"]');
        return {focus(options){assert.equal(options.preventScroll,true);detailsFocus=true;}};
    }
};
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
    document:{
        addEventListener(name,fn){
            assert.ok(['submit','click','change'].includes(name));
            if(name==='submit')submit=fn;
            else if(name==='click')click=fn;
            else change=fn;
        },
        getElementById(id){assert.equal(id,'cb-work-so-add');return details;},
        querySelectorAll(selector){assert.equal(selector,'[data-cb-so-project-context]');return [projectSelector];},
        querySelector(){return null;}
    },
    FormData:class{constructor(){this.map=new Map([['revision','0']]);}set(k,v){this.map.set(k,v);}get(k){return this.map.get(k);}},
    fetch:async (_url,options)=>{
        assert.equal(options.method,'POST');
        assert.equal(options.body.get('action'),'cb_work_service_invoice_ajax');
        return {ok:true,json:async()=>({success:true,data:{registration:{revision:1,state:'invoiced'}}})};
    }
};
vm.runInNewContext(source,context);
(async()=>{
    assert.equal(typeof click,'function');
    assert.equal(typeof change,'function');
    assert.equal(billingFields.hidden,true);
    assert.equal(checkbox.disabled,true);
    assert.equal(ruleOptions[1].disabled,false);
    assert.equal(ruleOptions[2].disabled,true);
    assert.equal(ruleOptions[3].disabled,true);
    contextSelected='customer';
    change({target:{closest(selector){assert.equal(selector,'[data-cb-so-project-context]');return projectSelector;}}});
    assert.equal(billingFields.hidden,false);
    assert.equal(checkbox.disabled,false);
    assert.equal(ruleOptions[1].disabled,true);
    assert.equal(ruleOptions[2].disabled,false);
    contextSelected='internal';
    change({target:{closest(){return projectSelector;}}});
    assert.equal(billingFields.hidden,true);
    assert.equal(checkbox.disabled,true);
    assert.equal(ruleOptions[1].disabled,false);
    ruleOptions[1].selected=true;
    rulePicker.value='17';
    serviceSelector.value='10';
    change({target:{closest(selector){return selector==='[data-cb-so-service-id]'?serviceSelector:null;}}});
    assert.equal(ruleOptions[1].disabled,true);
    assert.equal(rulePicker.value,'0');
    serviceSelector.value='9';
    let clickPrevented=false;
    click({
        target:{closest(selector){assert.equal(selector,'[data-cb-so-open]');return {href:'#cb-work-so-add'};}},
        preventDefault(){clickPrevented=true;}
    });
    assert.equal(clickPrevented,true);
    assert.equal(details.open,true);
    assert.equal(detailsFocus,true);
    assert.equal(detailsScroll,true);
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
    console.log('Service Operations AJAX and first-run interaction runtime: PASS');
})().catch(e=>{console.error(e);process.exitCode=1;});
