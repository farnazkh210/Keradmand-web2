"use strict";(self.webpackChunk_bitforms=self.webpackChunk_bitforms||[]).push([[8763],{14383:(e,l,t)=>{t.d(l,{FP:()=>n,Mm:()=>a,T5:()=>d,WK:()=>s,mG:()=>o,wX:()=>r});var i=t(87462),a=(t(35637),t(39492),function(e,l,t,a,s,n,d){var r=[].concat(e);if(d)r[n]=(0,i.Z)({},e[n],a),r.push({editItegration:!0}),l([].concat(r)),s.push(t);else{var o=[].concat(r);o.push(a),o.push({newItegration:!0}),l(o),s.push(t)}}),s=function(e){var l={},t=window.location.href.replace(window.opener.location.href+"/redirect","").split("&");t&&t.forEach((function(e){var t=e.split("=");t[1]&&(l[t[0]]=t[1])})),localStorage.setItem("__bitforms_"+e,JSON.stringify(l)),window.close()},n=function(e,l,t,a,s){var n=(0,i.Z)({},l);s?a?n.relatedlists[s-1].upload_field_map.splice(e,0,{}):n.relatedlists[s-1].field_map.splice(e,0,{}):a?n.upload_field_map.splice(e,0,{}):n.field_map.splice(e,0,{}),t((0,i.Z)({},n))},d=function(e,l,t,a,s){var n=(0,i.Z)({},l);s?a?n.relatedlists[s-1].upload_field_map.length>1&&n.relatedlists[s-1].upload_field_map.splice(e,1):n.relatedlists[s-1].field_map.length>1&&n.relatedlists[s-1].field_map.splice(e,1):a?n.upload_field_map.length>1&&n.upload_field_map.splice(e,1):n.field_map.length>1&&n.field_map.splice(e,1),t((0,i.Z)({},n))},r=function(e,l,t,a,s,n){var d=(0,i.Z)({},t);n?s?d.relatedlists[n-1].upload_field_map[l][e.target.name]=e.target.value:d.relatedlists[n-1].field_map[l][e.target.name]=e.target.value:s?d.upload_field_map[l][e.target.name]=e.target.value:d.field_map[l][e.target.name]=e.target.value,"custom"===e.target.value&&(n?d.relatedlists[n-1].field_map[l].customValue="":d.field_map[l].customValue=""),a((0,i.Z)({},d))},o=function(e,l,t,a,s){var n=(0,i.Z)({},t);s?n.relatedlists[s-1].field_map[l].customValue=e.target.value:n.field_map[l].customValue=e.target.value,a((0,i.Z)({},n))}},85813:(e,l,t)=>{t.d(l,{Z:()=>s});var i=t(35637),a=t(85893);function s(e){var l=e.step,t=e.saveConfig,s=e.edit,n=e.disabled;return s?(0,a.jsx)("div",{className:"txt-center w-9 mt-3",children:(0,a.jsx)("button",{onClick:t,id:"secondary-update-btn",className:"btn btcd-btn-lg green sh-sm flx",type:"button",disabled:n,children:(0,i.__)("Update","bitform")})}):(0,a.jsxs)("div",{className:"btcd-stp-page txt-center",style:{width:3===l&&"90%",height:3===l&&"100%"},children:[(0,a.jsx)("h2",{className:"ml-3",children:(0,i.__)("Successfully Integrated","bitform")}),(0,a.jsxs)("button",{onClick:t,id:"secondary-update-btn",className:"btn btcd-btn-lg green sh-sm",type:"button",children:[(0,i.__)("Finish & Save ","bitform")," ✔"]})]})}},27676:(e,l,t)=>{t.d(l,{GX:()=>r,Rx:()=>n});var i=t(35637),a=t(39492),s=t(52249),n=function(e,l,t,i,a){var n=(0,s.p$)(l),r=e.target,o=r.name,c=r.value;if(n[o]=c,"module"===o)n=d(n,t,i,a);t(n)},d=function(e,l,t,i){var a,n,d,c=(0,s.p$)(e);return c.field_map=[],null!=(a=c)&&null!=(n=a.default)&&null!=(d=n.fields)&&d[e.module]?c=o(c):r(c,l,t,i),c},r=function(e,l,t,n){var d=e.module;d&&(t(!0),(0,a.Z)({module:d},"bitforms_wc_refresh_fields").then((function(a){if(a&&a.success){var r=(0,s.p$)(e);a.data&&(r.default||(r.default={}),r.default.fields||(r.default.fields={}),r.default.fields[d]=a.data,r=o(r),l(r),n({show:!0,msg:(0,i.__)("Fields refreshed","bitform")}))}else n({show:!0,msg:(0,i.__)("Fields refresh failed. please try again","bitform")});t(!1)})).catch((function(){return t(!1)})))},o=function(e){var l=(0,s.p$)(e);return l.default.fields[l.module].required.forEach((function(e){l.field_map.find((function(l){return l.wcField===e}))||l.field_map.unshift({formField:"",wcField:e,required:!0})})),l.field_map.length||(l.field_map=[{formField:"",wcField:""}]),l}},79589:(e,l,t)=>{t.d(l,{Z:()=>_});var i=t(35637),a=t(68381),s=t(14383),n=t(27676),d=t(87462),r=t(2804),o=t(54017),c=t(23312),u=t(52249),m=t(80410),f=t(88530),p=t(85893);function h(e){var l=e.i,t=e.formFields,a=e.field,s=e.wcConf,n=e.setWcConf,h=e.uploadFields,_=!0===a.required,b=(0,r.sJ)(o.hi).isPro,x=function(e,l){var t=(0,u.p$)(s);h?t.upload_field_map[l][e.target.name]=e.target.value:t.field_map[l][e.target.name]=e.target.value,"custom"===e.target.value&&(t.field_map[l].customValue=""),n(t)};return(0,p.jsxs)("div",{className:"flx mt-2 mr-1",children:[(0,p.jsxs)("div",{className:"flx integ-fld-wrp",children:[(0,p.jsxs)("select",{className:"btcd-paper-inp mr-2",name:"formField",value:a.formField||"",onChange:function(e){return x(e,l)},children:[(0,p.jsx)("option",{value:"",children:(0,i.__)("Select Field","bitform")}),(0,p.jsx)("optgroup",{label:"Form Fields",children:h?t.map((function(e){return"file-up"===e.type&&(0,p.jsx)("option",{value:e.key,children:e.name},"ff-zhcrm-"+e.key)})):t.map((function(e){return"file-up"!==e.type&&(0,p.jsx)("option",{value:e.key,children:e.name},"ff-zhcrm-"+e.key)}))}),!h&&(0,p.jsx)("option",{value:"custom",children:(0,i.__)("Custom...","bitform")}),!h&&(0,p.jsxs)("optgroup",{label:"General Smart Codes "+(b?"":"(PRO)"),children:[" ",b&&m.C.map((function(e){return(0,p.jsx)("option",{value:e.name,children:e.label},"ff-zhcrm-"+e.name)}))]})]}),"custom"===a.formField&&(0,p.jsx)(f.Z,{onChange:function(e){return function(e,l){var t=(0,u.p$)(s);h?t.upload_field_map[l].customValue=e.target.value:t.field_map[l].customValue=e.target.value,n(t)}(e,l)},label:(0,i.__)("Custom Value","bitform"),className:"mr-2",type:"text",value:a.customValue,placeholder:(0,i.__)("Custom Value","bitform")}),(0,p.jsxs)("select",{className:"btcd-paper-inp",name:"wcField",value:a.wcField||"",onChange:function(e){return x(e,l)},disabled:_,children:[(0,p.jsx)("option",{value:"",children:(0,i.__)("Select Field","bitform")}),Object.values(s.default.fields[s.module][h?"uploadFields":"fields"]).map((function(e){if(_){if(e.required&&e.fieldKey===a.wcField)return(0,p.jsx)("option",{value:e.fieldKey,children:e.fieldName},e.fieldKey+"-1")}else if(!e.required)return(0,p.jsx)("option",{value:e.fieldKey,children:e.fieldName},e.fieldKey+"-1")}))]})]}),!_&&(0,p.jsxs)(p.Fragment,{children:[(0,p.jsx)("button",{onClick:function(){return function(e){var l=(0,d.Z)({},s);h?l.upload_field_map.splice(e,0,{}):l.field_map.splice(e,0,{}),n(l)}(l)},className:"icn-btn sh-sm ml-2 mr-1",type:"button",children:"+"}),(0,p.jsx)("button",{onClick:function(){return function(e){var l=(0,u.p$)(s);h?l.upload_field_map.length>1&&l.upload_field_map.splice(e,1):l.field_map.length>1&&l.field_map.splice(e,1),n(l)}(l)},className:"icn-btn sh-sm ml-2",type:"button","aria-label":"btn",children:(0,p.jsx)(c.Z,{})})]})]})}function _(e){var l,t,d,r,o,c,u=e.formFields,m=e.handleInput,f=e.wcConf,_=e.setWcConf,b=e.isLoading,x=e.setisLoading,v=e.setSnackbar;return(0,p.jsxs)(p.Fragment,{children:[(0,p.jsx)("br",{}),(0,p.jsx)("b",{className:"wdt-100 d-in-b",children:(0,i.__)("Module:","bitform")}),(0,p.jsxs)("select",{onChange:m,name:"module",value:f.module,className:"btcd-paper-inp w-7",children:[(0,p.jsx)("option",{value:"",children:(0,i.__)("Select Module","bitform")}),(0,p.jsx)("option",{value:"customer",children:"Customer"}),(0,p.jsx)("option",{value:"product",children:"Product"})]}),(0,p.jsx)("br",{}),(0,p.jsx)("br",{}),b&&(0,p.jsx)(a.default,{style:{display:"flex",justifyContent:"center",alignItems:"center",height:100,transform:"scale(0.7)"}}),(null==(l=f.default)||null==(t=l.fields)||null==(d=t[f.module])?void 0:d.fields)&&(0,p.jsxs)(p.Fragment,{children:[(0,p.jsxs)("div",{className:"mt-4",children:[(0,p.jsx)("b",{className:"wdt-100",children:(0,i.__)("Map Fields","bitform")}),(0,p.jsx)("button",{onClick:function(){return(0,n.GX)(f,_,x,v)},className:"icn-btn sh-sm ml-2 mr-2 tooltip",style:{"--tooltip-txt":"'"+(0,i.__)("Refresh Fields","bitform")+"'"},type:"button",disabled:b,children:"↻"})]}),(0,p.jsx)("div",{className:"btcd-hr mt-1"}),(0,p.jsxs)("div",{className:"flx flx-around mt-2 mb-1",children:[(0,p.jsx)("div",{className:"txt-dp",children:(0,p.jsx)("b",{children:(0,i.__)("Form Fields","bitform")})}),(0,p.jsx)("div",{className:"txt-dp",children:(0,p.jsx)("b",{children:(0,i.__)("WooCommerce Fields","bitform")})})]}),f.field_map.map((function(e,l){return(0,p.jsx)(h,{i:l,field:e,wcConf:f,formFields:u,setWcConf:_},"wc-m-"+(l+9))})),(0,p.jsx)("div",{className:"txt-center  mt-2",style:{marginRight:85},children:(0,p.jsx)("button",{onClick:function(){return(0,s.FP)(f.field_map.length,f,_)},className:"icn-btn sh-sm",type:"button",children:"+"})})]}),(null==(r=f.default)||null==(o=r.fields)||null==(c=o[f.module])?void 0:c.uploadFields)&&(0,p.jsxs)(p.Fragment,{children:[(0,p.jsxs)("div",{className:"mt-4",children:[(0,p.jsx)("b",{className:"wdt-100",children:(0,i.__)("Map File Upload Fields","bitform")}),(0,p.jsx)("button",{onClick:function(){return(0,n.GX)(f,_,x,v)},className:"icn-btn sh-sm ml-2 mr-2 tooltip",style:{"--tooltip-txt":"'"+(0,i.__)("Refresh Fields","bitform")+"'"},type:"button",disabled:b,children:"↻"})]}),(0,p.jsx)("div",{className:"btcd-hr mt-1"}),(0,p.jsxs)("div",{className:"flx flx-around mt-2 mb-1",children:[(0,p.jsx)("div",{className:"txt-dp",children:(0,p.jsx)("b",{children:(0,i.__)("Form Fields","bitform")})}),(0,p.jsx)("div",{className:"txt-dp",children:(0,p.jsx)("b",{children:(0,i.__)("WooCommerce Fields","bitform")})})]}),f.upload_field_map.map((function(e,l){return(0,p.jsx)(h,{i:l,field:e,wcConf:f,formFields:u,setWcConf:_,uploadFields:!0},"wc-m-"+(l+9))})),(0,p.jsx)("div",{className:"txt-center  mt-2",style:{marginRight:85},children:(0,p.jsx)("button",{onClick:function(){return(0,s.FP)(f.field_map.length,f,_,!0)},className:"icn-btn sh-sm",type:"button",children:"+"})})]})]})}},88530:(e,l,t)=>{t.d(l,{Z:()=>a});var i=t(85893);const a=function(e){var l=e.label,t=e.onChange,a=e.value,s=e.disabled,n=e.type,d=e.textarea,r=e.className;return(0,i.jsxs)("label",{className:"btcd-mt-inp "+r,children:[!d&&(0,i.jsx)("input",{type:void 0===n?"text":n,onChange:t,placeholder:" ",disabled:s,value:a}),d&&(0,i.jsx)("textarea",{type:void 0===n?"text":n,onChange:t,placeholder:" ",disabled:s,value:a}),(0,i.jsx)("span",{children:l})]})}}}]);