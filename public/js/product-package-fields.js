(function(root){
  function fromVariations(groups){
    const weights=[],widths=[],heights=[],conditions=[];
    const number='(\\d+(?:\\.\\d+)?)';
    const weightPattern=new RegExp('^\\s*'+number+'\\s*(?:[-–]\\s*'+number+')?\\s*(mg|g|kg|oz|lb)?\\s*$','i');
    const dimensionPattern=new RegExp('^\\s*'+number+'\\s*[x×]\\s*'+number+'(?:\\s*[x×]\\s*'+number+')?\\s*(?:cm)?\\s*$','i');
    function collect(name,value){
      const key=String(name).trim().toLowerCase(),text=String(value??'').trim();
      if(!text||text==='Not applicable')return;
      if(/^(?:weights?|grams?)$/.test(key)){
        const match=text.match(weightPattern);if(!match)return;
        const factor={mg:.001,g:1,kg:1000,oz:28.349523125,lb:453.59237}[(match[3]||'g').toLowerCase()];
        const min=Number(match[1])*factor,max=Number(match[2]||match[1])*factor;
        if(min>0&&max>=min)weights.push(max);
      }else if(/^dimensions?$/.test(key)){
        const match=text.match(dimensionPattern);if(match){const values=match.slice(1).filter(Boolean).map(Number);if(values.every(value=>value>0)){widths.push(values.at(-2));heights.push(values.at(-1));}}
      }else if(/^(?:width|height)(?:\s*\(cm\))?$/.test(key)){
        const value=Number(text.replace(/\s*cm$/i,''));if(value>0&&Number.isFinite(value))(key.startsWith('width')?widths:heights).push(value);
      }else if(key==='condition'&&/^(new|used)$/i.test(text))conditions.push(text.toLowerCase());
    }
    (groups||[]).forEach(group=>(group.options||[]).forEach(option=>{
      Object.entries(option.attributes||{}).forEach(([name,value])=>collect(name,value));
      if(group.type==='combinations'){
        (group.axes||[]).forEach(axis=>{const main=(axis.options||[]).find(value=>value.value===option.attributes?.[axis.name]);if(main?.variation_name)collect(main.variation_name,main.option_value);});
      }else collect(option.variation_name||group.name,option.option_value||option.value);
    }));
    const result={};
    // Checkout currently reads the product weight, so retain the largest variant weight.
    if(weights.length){result.weight_grams=Math.max(1,Math.ceil(Math.max(...weights)));result.weight_grams_max=null;}
    if(widths.length)result.width_cm=Math.max(...widths);
    if(heights.length)result.height_cm=Math.max(...heights);
    if(conditions.length)result.condition=conditions.includes('used')?'used':'new';
    return result;
  }
  if(typeof module!=='undefined')module.exports={fromVariations};else root.ProductPackageFields={fromVariations};
})(typeof window==='undefined'?globalThis:window);
