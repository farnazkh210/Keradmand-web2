"use strict";(self.webpackChunk_bitforms=self.webpackChunk_bitforms||[]).push([[8319],{14383:(e,t,a)=>{a.d(t,{FP:()=>s,Mm:()=>n,T5:()=>r,WK:()=>l,mG:()=>d,wX:()=>o});var i=a(87462),n=(a(35637),a(39492),function(e,t,a,n,l,s,r){var o=[].concat(e);if(r)o[s]=(0,i.Z)({},e[s],n),o.push({editItegration:!0}),t([].concat(o)),l.push(a);else{var d=[].concat(o);d.push(n),d.push({newItegration:!0}),t(d),l.push(a)}}),l=function(e){var t={},a=window.location.href.replace(window.opener.location.href+"/redirect","").split("&");a&&a.forEach((function(e){var a=e.split("=");a[1]&&(t[a[0]]=a[1])})),localStorage.setItem("__bitforms_"+e,JSON.stringify(t)),window.close()},s=function(e,t,a,n,l){var s=(0,i.Z)({},t);l?n?s.relatedlists[l-1].upload_field_map.splice(e,0,{}):s.relatedlists[l-1].field_map.splice(e,0,{}):n?s.upload_field_map.splice(e,0,{}):s.field_map.splice(e,0,{}),a((0,i.Z)({},s))},r=function(e,t,a,n,l){var s=(0,i.Z)({},t);l?n?s.relatedlists[l-1].upload_field_map.length>1&&s.relatedlists[l-1].upload_field_map.splice(e,1):s.relatedlists[l-1].field_map.length>1&&s.relatedlists[l-1].field_map.splice(e,1):n?s.upload_field_map.length>1&&s.upload_field_map.splice(e,1):s.field_map.length>1&&s.field_map.splice(e,1),a((0,i.Z)({},s))},o=function(e,t,a,n,l,s){var r=(0,i.Z)({},a);s?l?r.relatedlists[s-1].upload_field_map[t][e.target.name]=e.target.value:r.relatedlists[s-1].field_map[t][e.target.name]=e.target.value:l?r.upload_field_map[t][e.target.name]=e.target.value:r.field_map[t][e.target.name]=e.target.value,"custom"===e.target.value&&(s?r.relatedlists[s-1].field_map[t].customValue="":r.field_map[t].customValue=""),n((0,i.Z)({},r))},d=function(e,t,a,n,l){var s=(0,i.Z)({},a);l?s.relatedlists[l-1].field_map[t].customValue=e.target.value:s.field_map[t].customValue=e.target.value,n((0,i.Z)({},s))}},85813:(e,t,a)=>{a.d(t,{Z:()=>l});var i=a(35637),n=a(85893);function l(e){var t=e.step,a=e.saveConfig,l=e.edit,s=e.disabled;return l?(0,n.jsx)("div",{className:"txt-center w-9 mt-3",children:(0,n.jsx)("button",{onClick:a,id:"secondary-update-btn",className:"btn btcd-btn-lg green sh-sm flx",type:"button",disabled:s,children:(0,i.__)("Update","bitform")})}):(0,n.jsxs)("div",{className:"btcd-stp-page txt-center",style:{width:3===t&&"90%",height:3===t&&"100%"},children:[(0,n.jsx)("h2",{className:"ml-3",children:(0,i.__)("Successfully Integrated","bitform")}),(0,n.jsxs)("button",{onClick:a,id:"secondary-update-btn",className:"btn btcd-btn-lg green sh-sm",type:"button",children:[(0,i.__)("Finish & Save ","bitform")," ✔"]})]})}},8319:(e,t,a)=>{a.r(t),a.d(t,{default:()=>f});var i=a(87462),n=a(67294),l=a(5977),s=(a(2804),a(54017),a(35637)),r=a(35001),o=a(14383),d=a(85813),c=a(63538),u=a(54107),m=a(85893);const f=function(e){var t=e.formFields,a=e.setIntegration,f=e.integrations,p=e.allIntegURL,_=(0,l.k6)(),h=(0,l.UO)(),b=h.id,g=h.formID,v=(0,n.useState)((0,i.Z)({},f[b])),x=v[0],j=v[1],w=(0,n.useState)(!1),C=w[0],y=w[1],F=(0,n.useState)({show:!1}),N=F[0],Z=F[1];return(0,m.jsxs)("div",{style:{width:900},children:[(0,m.jsx)(r.Z,{snack:N,setSnackbar:Z}),(0,m.jsxs)("div",{className:"flx mt-3",children:[(0,m.jsx)("b",{className:"wdt-200 d-in-b",children:(0,s.__)("Integration Name:","bit-integrations")}),(0,m.jsx)("input",{className:"btcd-paper-inp w-5",onChange:function(e){return handleInput(e,x,j)},name:"name",value:x.name,type:"text",placeholder:(0,s.__)("Integration Name...","bit-integrations")})]}),(0,m.jsx)("br",{}),(0,m.jsx)(u.Z,{formID:g,formFields:t,handleInput:function(e){function t(t){return e.apply(this,arguments)}return t.toString=function(){return e.toString()},t}((function(e){return handleInput(e,x,j,y,Z)})),twilioConf:x,setTwilioConf:j,isLoading:C,setIsLoading:y,setSnackbar:Z}),(0,m.jsx)(d.Z,{edit:!0,saveConfig:function(){(0,c.Pd)(x)?(0,o.Mm)(f,a,p,x,_,b,1):Z({show:!0,msg:(0,s.__)("Please map mandatory fields","bit-integrations")})}}),(0,m.jsx)("br",{})]})}},63538:(e,t,a)=>{a.d(t,{P_:()=>o,Pd:()=>r,Rx:()=>s});var i=a(87462),n=a(35637),l=a(39492),s=function(e,t,a,n,l,s,r,o){var d=(0,i.Z)({},t),c=e.target.name;""!==e.target.value?d[c]=e.target.value:delete d[c],a((0,i.Z)({},d))},r=function(e){return!((null!=e&&e.field_map?e.field_map.filter((function(e){return!e.formField||!e.twilioField||"custom"===!e.formField&&!e.customValue})):[]).length>0)},o=function(e,t,a,s,r,o){if(e.sid&&e.token&&e.from_num){a({}),r(!0);var d={sid:e.sid,token:e.token,from_num:e.from_num};(0,l.Z)(d,"bitforms_twilio_authorization").then((function(e){return e})).then((function(a){if(a&&a.success){var l=(0,i.Z)({},e);l.tokenDetails=a.data,t(l),s(!0),o({show:!0,msg:(0,n.__)("Authorized Successfully","bitform")})}else a&&a.data&&a.data.data||!a.success&&"string"===typeof a.data?o({show:!0,msg:""+(0,n.__)("Authorization failed Cause:","bitform")+(a.data.data||a.data)+". "+(0,n.__)("please try again","bitform")}):o({show:!0,msg:(0,n.__)("Authorization failed. please try again","bitform")});r(!1)}))}else a({sid:e.sid?"":(0,n.__)("Account SID can't be empty","bitform"),token:e.token?"":(0,n.__)("Auth Token can't be empty","bitform"),from_num:e.from_num?"":(0,n.__)("Phone number can't be empty","bitform")})}},54107:(e,t,a)=>{a.d(t,{Z:()=>m});a(67294);var i=a(35637),n=a(2804),l=a(88530),s=a(87462),r=function(e,t,a,i){var n=(0,s.Z)({},a);n.field_map[t][e.target.name]=e.target.value,"custom"===e.target.value&&(n.field_map[t].customValue=""),i((0,s.Z)({},n))},o=a(80410),d=a(54017),c=a(85893);function u(e){var t=e.i,a=e.formFields,u=e.field,m=e.twilioConf,f=e.setTwilioConf,p=(0,n.sJ)(d.hi).isPro;return(0,c.jsx)("div",{className:"flx mt-2 mb-2 btcbi-field-map",children:(0,c.jsx)("div",{className:"pos-rel flx",children:(0,c.jsxs)("div",{className:"flx integ-fld-wrp",children:[(0,c.jsxs)("select",{className:"btcd-paper-inp mr-2",name:"formField",value:u.formField||"",onChange:function(e){return r(e,t,m,f)},children:[(0,c.jsx)("option",{value:"",children:(0,i.__)("Select Field","bit-integrations")}),(0,c.jsx)("optgroup",{label:"Form Fields",children:null==a?void 0:a.map((function(e){return(0,c.jsx)("option",{value:e.key,children:e.name},"ff-rm-"+e.key)}))}),(0,c.jsx)("option",{value:"custom",children:(0,i.__)("Custom...","bit-integrations")}),(0,c.jsx)("optgroup",{label:"General Smart Codes "+(p?"":"(PRO)"),children:p&&(null==o.C?void 0:o.C.map((function(e){return(0,c.jsx)("option",{value:e.name,children:e.label},"ff-rm-"+e.name)})))})]}),"custom"===u.formField&&(0,c.jsx)(l.Z,{onChange:function(e){return function(e,t,a,i){var n=(0,s.Z)({},a);n.field_map[t].customValue=e.target.value,i((0,s.Z)({},n))}(e,t,m,f)},label:(0,i.__)("Custom Value","bit-integrations"),className:"mr-2",type:"text",value:u.customValue,placeholder:(0,i.__)("Custom Value","bit-integrations")}),(0,c.jsxs)("select",{className:"btcd-paper-inp",disabled:!0,name:"twilioField",value:u.twilioField,onChange:function(e){return r(e,t,m,f)},children:[(0,c.jsx)("option",{value:"",children:(0,i.__)("Select Field","bit-integrations")}),null==m?void 0:m.twilioFields.map((function(e){var t=e.key,a=e.label;return(0,c.jsx)("option",{value:t,children:a},t)}))]})]})})})}function m(e){var t=e.formFields,a=(e.handleInput,e.twilioConf),n=e.setTwilioConf,l=(e.isLoading,e.setIsLoading,e.setSnackbar);return(0,c.jsxs)(c.Fragment,{children:[(0,c.jsx)("br",{}),(0,c.jsx)("div",{className:"mt-5",children:(0,c.jsx)("b",{className:"wdt-100",children:(0,i.__)("Field Map","bit-integrations")})}),(0,c.jsx)("div",{className:"btcd-hr mt-1"}),(0,c.jsxs)("div",{className:"flx flx-around mt-2 mb-2 btcbi-field-map-label",children:[(0,c.jsx)("div",{className:"txt-dp",children:(0,c.jsx)("b",{children:(0,i.__)("Form Fields","bit-integrations")})}),(0,c.jsx)("div",{className:"txt-dp",children:(0,c.jsx)("b",{children:(0,i.__)("Twilio Fields","bit-integrations")})})]}),null==a?void 0:a.field_map.map((function(e,i){return(0,c.jsx)(u,{i,field:e,twilioConf:a,formFields:t,setTwilioConf:n,setSnackbar:l},"rp-m-"+(i+9))})),(0,c.jsx)("br",{})]})}},88530:(e,t,a)=>{a.d(t,{Z:()=>n});var i=a(85893);const n=function(e){var t=e.label,a=e.onChange,n=e.value,l=e.disabled,s=e.type,r=e.textarea,o=e.className;return(0,i.jsxs)("label",{className:"btcd-mt-inp "+o,children:[!r&&(0,i.jsx)("input",{type:void 0===s?"text":s,onChange:a,placeholder:" ",disabled:l,value:n}),r&&(0,i.jsx)("textarea",{type:void 0===s?"text":s,onChange:a,placeholder:" ",disabled:l,value:n}),(0,i.jsx)("span",{children:t})]})}}}]);