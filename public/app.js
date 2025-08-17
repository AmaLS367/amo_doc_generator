(function(){
  const qs = new URLSearchParams(location.search);
  const leadId = Number(qs.get('lead_id')||0);
  const API = '/api/generate.php';

  const $ = s=>document.querySelector(s);
  const tbody = $('#tbl tbody');
  const sumEl = $('#sum'), totalEl = $('#total'), cntEl = $('#cnt'), wordsEl = $('#totalWords');
  const discountEl = $('#discount'), templateEl = $('#template');
  const genBtn = $('#gen'), linkA = $('#link');

  const fmt = n => (Number(n)||0).toLocaleString('ru-RU');
  function toast(msg){ const t=$('#toast'); t.textContent=msg; t.hidden=false; setTimeout(()=>t.hidden=true,2500); }

  function addRow(name='', unitPrice='', qty='1', discP=''){
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="num"><div class="ph">${'${row_num}'}</div></td>
      <td class="left">
        <div class="cell">
          <div class="ph">${'${услуга_название}'}</div>
          <input class="n" placeholder="Наименование" value="${name}">
        </div>
      </td>
      <td>
        <div class="cell">
          <div class="ph right">${'${row_qty}'}</div>
          <input class="q" type="number" min="1" step="1" value="${qty}">
        </div>
      </td>
      <td>
        <div class="cell">
          <div class="ph right">${'${row_price}'}</div>
          <input class="u" type="number" min="0" step="1" value="${unitPrice}">
        </div>
      </td>
      <td>
        <div class="cell">
          <div class="ph right">${'${row_discount}'}</div>
          <input class="d" type="number" min="0" step="0.01" value="${discP}">
        </div>
      </td>
      <td class="right">
        <div class="cell">
          <div class="ph right">${'${row_sum}'}</div>
          <div class="s">0</div>
        </div>
      </td>
      <td><button class="icon-btn danger" title="Удалить">✕</button></td>`;
    tr.querySelector('.icon-btn').onclick = ()=>{ tr.remove(); recalc(); };
    tr.querySelectorAll('input').forEach(i=> i.oninput = recalc);
    tbody.appendChild(tr);
    recalc();
  }

  function rowData(tr){
    const name = tr.querySelector('.n').value.trim();
    const qty  = Math.max(1, Number(tr.querySelector('.q').value||1));
    const unit = Math.max(0, Number(tr.querySelector('.u').value||0));
    const dp   = Math.max(0, Number(tr.querySelector('.d').value||0));
    const gross = qty * unit;
    let after = gross;
    if (dp>0) after = Math.round(gross * (1 - dp/100));
    return {name, qty, unit, dp, gross, after};
  }

  function products(){
    const arr=[];
    tbody.querySelectorAll('tr').forEach(tr=>{
      const r = rowData(tr);
      if(r.name) arr.push({
        name: r.name,
        qty: r.qty,
        unit_price: r.unit,
        discount_percent: r.dp
      });
    });
    return arr;
  }

  function recalc(){
    let sumGross = 0, sumAfter = 0, idx = 1;
    tbody.querySelectorAll('tr').forEach(tr=>{
      const r = rowData(tr);
      tr.querySelector('.s').textContent = fmt(r.after);
      tr.querySelector('.num').textContent = idx++; 
      sumGross += r.gross;
      sumAfter += r.after;
    });
    const globalDisc = Number(discountEl.value||0);
    const total = Math.max(sumAfter - globalDisc, 0);
    cntEl.textContent = tbody.querySelectorAll('tr').length;
    sumEl.textContent = fmt(sumGross);
    totalEl.textContent = fmt(total);
    wordsEl.textContent = toWords(total);
    linkA.style.display='none';
  }

  function morph(n,f1,f2,f5){ n=Math.abs(n)%100; const n1=n%10;
    if(n>10&&n<20) return f5; if(n1>1&&n1<5) return f2; if(n1==1) return f1; return f5; }
  function toWords(num){
    if(num===0) return 'ноль рублей';
    const w1=['','один','два','три','четыре','пять','шесть','семь','восемь','девять'];
    const w1f=['','одна','две','три','четыре','пять','шесть','семь','восемь','девять'];
    const w10=['десять','одиннадцать','двенадцать','тринадцать','четырнадцать','пятнадцать','шестнадцать','семнадцать','восемнадцать','девятнадцать'];
    const w2=['','десять','двадцать','тридцать','сорок','пятьдесят','шестьдесят','семьдесят','восемьдесят','девяносто'];
    const w3=['','сто','двести','триста','четыреста','пятьсот','шестьсот','семьсот','восемьсот','девятьсот'];
    const units=[['рубль','рубля','рублей',0],['тысяча','тысячи','тысяч',1],['миллион','миллиона','миллионов',0]];
    let parts=[], i=0;
    while(num>0 && i<units.length){
      const n = num%1000; if(n){
        const g=units[i][3]; const s=[];
        s.push(w3[Math.trunc(n/100)]);
        const t=n%100;
        if(t>=10 && t<20){ s.push(w10[t-10]); }
        else{ s.push(w2[Math.trunc(t/10)]); s.push((g?w1f:w1)[t%10]); }
        s.push(morph(n,units[i][0],units[i][1],units[i][2]));
        parts.push(s.filter(Boolean).join(' '));
      }
      num = Math.trunc(num/1000); i++;
    }
    return parts.reverse().join(' ').trim();
  }

  $('#add').onclick = ()=> addRow();
  $('#gen').onclick = async ()=>{
    const body = {
      lead_id: leadId,
      template: templateEl.value,
      products: products(),
      discount: Number(discountEl.value||0)
    };
    if(!body.products.length){ toast('Добавьте хотя бы одну позицию'); return; }
    genBtn.disabled = true; genBtn.textContent = 'Генерирую…';
    try{
      const r = await fetch(API+'/api/generate.php', {
        method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body)
      });
      const text = await r.text(); let j=null;
      try{ j = JSON.parse(text); }catch(_){}
      if(!r.ok || !j || !j.url) throw new Error((j&&j.error) || ('HTTP '+r.status+' '+text.slice(0,120)));
      linkA.href = j.url; linkA.style.display='inline-block'; linkA.click();
      toast('Документ создан');
    }catch(e){ toast('Ошибка: '+e.message); }
    finally{ genBtn.disabled=false; genBtn.textContent='Сформировать'; }
  };
  discountEl.oninput = recalc; templateEl.onchange = ()=>{ linkA.style.display='none'; };

  (async function(){
    if(!leadId){ addRow(); return; }
    try{
      const r = await fetch(API+'/api/prefill.php?lead_id='+leadId);
      const j = await r.json();
      (j.products||[]).forEach(p=>{
        addRow(p.name||'', p.unit_price||p.price||0, p.qty||p.quantity||1, p.discount_percent||0);
      });
      discountEl.value = j.discount||0;
      templateEl.value = j.template||'order';
      if(!tbody.children.length) addRow();
    }catch(_){ addRow(); }
    recalc();
  })();
})();
