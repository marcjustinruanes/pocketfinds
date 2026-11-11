(function (root) {
  const NONE = 'Not applicable';
  const reserved = ['stock','price','image','existing_image','image_key','color','variation_name','option_value'];
  function build(name, parents, fields, originalOrder = []) {
    const options = parents.filter(parent => parent.value.trim());
    if (!options.length) return [];
    if (new Set(options.map(option => option.value.toLowerCase())).size !== options.length) throw new Error('Give each main option a different name.');
    options.forEach(parent=>{
      const children=parent.children || [];
      if(children.some(child=>child.stock===''||child.stock==null||!Number.isInteger(Number(child.stock))||Number(child.stock)<0))throw new Error(parent.value+': set stock for every option. Enter 0 for an unavailable option.');
      const required=fields.filter(field=>field!==name&&children[0]?.attributes?.[field]?.trim()&&children[0].attributes[field]!==NONE);
      children.forEach((child,index)=>{
        const missing=required.filter(field=>!child.attributes?.[field]?.trim()||child.attributes[field]===NONE);
        if(missing.length)throw new Error(parent.value+', option '+(index+1)+': complete '+missing.join(', ')+'. Use the same measurement fields as the first option.');
      });
    });
    const active = fields.filter(field => field !== name && options.some(parent => parent.children?.some(child => child.attributes?.[field]?.trim())));
    if (!active.length && !options.some(parent => parent.children?.length)) {
      return [{name, options:options.map(({children, ...option}) => option)}];
    }
    if (!active.length) throw new Error('Enter a size, weight or dimensions for each added option, or remove it.');
    const axisNames = [...new Set([...originalOrder.filter(key => key === name || active.includes(key)), name, ...active])];
    const rows = [];
    options.forEach(parent => {
      const children = parent.children?.length ? parent.children : [{attributes:{}, stock:parent.stock, price:parent.price}];
      children.forEach(child => {
        const attributes = {[name]:parent.value};
        active.forEach(field => attributes[field] = child.attributes?.[field]?.trim() || NONE);
        const row = {attributes, value:axisNames.map(field => attributes[field]).join(' / '), stock:child.stock};
        if (child.price !== '' && child.price != null) row.price = Number(child.price);
        rows.push(row);
      });
    });
    if (rows.length > 500) throw new Error('Use up to 500 sellable options per product.');
    if (new Set(rows.map(row => row.value)).size !== rows.length) throw new Error('Each size, weight and dimension option must be unique within its main option.');
    if (rows.some(row => row.value.length > 150)) throw new Error('Use shorter option names (up to 150 characters together).');
    const axes = axisNames.map(field => ({name:field,options:field === name
      ? options.map(parent => Object.fromEntries(Object.entries(parent).filter(([key]) => key === 'value' || reserved.includes(key) && !['stock','price'].includes(key))))
      : [...new Set(rows.map(row => row.attributes[field]))].map(value => ({value}))}));
    if (axes.some(axis => axis.options.length > 20)) throw new Error('Use up to 20 choices for each option type.');
    return [{name:'Options',type:'combinations',selective:true,axes,options:rows}];
  }
  const api = {build,NONE};
  if (typeof module !== 'undefined') module.exports = api;
  else root.NestedProductVariants = api;
})(typeof window === 'undefined' ? globalThis : window);
