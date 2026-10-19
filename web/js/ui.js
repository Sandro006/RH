const ui={escape(v=''){
    return String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]))},
    status(v=''){const s=String(v),
    low=s.toLowerCase();
    let c='';
    if(/valid|retenue|actif/.test(low))c='success';
    else if(/refus|annul|retir/.test(low))c='danger';
    else if(/contrôle|validation|consultation|corriger|suspend|entretien|évaluation/.test(low))c='warning';
    return `<span class="badge ${c}">${ui.escape(s)}</span>`},
    date(v){
        return v?new Intl.DateTimeFormat('fr-FR').format(new Date(v)): '—'},
    money(v){
        return v==null?'—':new Intl.NumberFormat('fr-FR').format(v)
    },
        toast(message){
            const e=document.createElement('div');
            e.className='toast';
            e.textContent=message;
            document.body.append(e);
            setTimeout(()=>e.remove(),3000)
        },
            error(e){const x=document.querySelector('[data-error]');
                if(x){
                    x.textContent=e.message||e;x.hidden=false
                }
            }
};
