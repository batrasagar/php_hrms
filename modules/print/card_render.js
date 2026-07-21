/* ── ID Card template renderer ─────────────────────────────────────────────
   Shared by the designer (card_designer.php) and print page (card_print.php)
   so the on-screen preview and the printed card are pixel-identical.

   Template layout JSON: { front:{elements:[...]}, back:{elements:[...]}|null }
   All geometry is in millimetres; font sizes in points. Z-order = array order.

   opts: { unit:'px', zoom: <px per mm> }  → screen preview
         { unit:'mm' }                     → print (physical units)
   entry: flat map of field key → string, plus __photo/__signature/__issuer_sign URLs.
─────────────────────────────────────────────────────────────────────────── */
(function (global) {
  'use strict';

  var PT2MM = 25.4 / 72;

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  // mm → css length for the current mode
  function L(v, opts) {
    return opts.unit === 'mm' ? (+v || 0) + 'mm' : ((+v || 0) * opts.zoom) + 'px';
  }
  // pt font size → css for the current mode
  function F(pt, opts) {
    return opts.unit === 'mm' ? (+pt || 8) + 'pt' : ((+pt || 8) * PT2MM * opts.zoom) + 'px';
  }

  function applyTokens(text, entry) {
    return String(text == null ? '' : text).replace(/\{(\w+)\}/g, function (_m, k) {
      var v = entry[k];
      return (v === undefined || v === null) ? '' : String(v);
    });
  }

  function codeValue(el, entry) {
    if (el.source === 'aadhaar') return entry.aadhaar || '';
    if (el.source === 'custom')  return applyTokens(el.content || '', entry);
    return entry.code || '';                       // default: employee code
  }

  function baseBox(el, opts, extra) {
    var s = 'position:absolute;left:' + L(el.x, opts) + ';top:' + L(el.y, opts) + ';';
    if (el.rotation) s += 'transform:rotate(' + (+el.rotation || 0) + 'deg);transform-origin:top left;';
    return s + (extra || '');
  }

  function textStyle(el, opts) {
    var s = 'font-size:' + F(el.fontSize, opts) + ';';
    s += 'font-family:' + (el.fontFamily || 'Arial, sans-serif') + ';';
    s += 'color:' + (el.color || '#000') + ';line-height:1.18;';
    if (el.bold)   s += 'font-weight:700;';
    if (el.italic) s += 'font-style:italic;';
    s += 'text-align:' + (el.align || 'left') + ';';
    if (+el.w > 0) { s += 'width:' + L(el.w, opts) + ';overflow:hidden;'; }
    else           { s += 'white-space:nowrap;'; }
    if (+el.h > 0) { s += 'height:' + L(el.h, opts) + ';'; }
    return s;
  }

  // One element → HTML. extraAttrs lets the designer tag elements for hit-testing.
  function elementHtml(el, entry, opts, extraAttrs) {
    var a = extraAttrs || '';
    switch (el.type) {

      case 'field': {
        var v = entry[el.key];
        v = (v === undefined || v === null) ? '' : String(v);
        var txt = (el.prefix || '') + v;
        if (!txt && !opts.designer) return '';            // skip empties at print time
        return '<div ' + a + ' style="' + baseBox(el, opts, textStyle(el, opts)) + '">'
             + esc(txt || (opts.designer ? '[' + el.key + ']' : '')) + '</div>';
      }

      case 'text': {
        var t = applyTokens(el.content || '', entry);
        if (!t && !opts.designer) return '';
        return '<div ' + a + ' style="' + baseBox(el, opts, textStyle(el, opts)) + '">'
             + esc(t || (opts.designer ? '[text]' : '')) + '</div>';
      }

      case 'line': {
        var th = L(el.thickness || 0.3, opts), ln = L(el.len || 10, opts);
        var dim = el.orient === 'v' ? ('width:' + th + ';height:' + ln) : ('width:' + ln + ';height:' + th);
        return '<div ' + a + ' style="' + baseBox(el, opts, dim + ';background:' + (el.color || '#000') + ';') + '"></div>';
      }

      case 'rect': {
        var s = 'width:' + L(el.w, opts) + ';height:' + L(el.h, opts) + ';';
        s += 'background:' + (el.fill && el.fill !== 'none' ? el.fill : 'transparent') + ';';
        if (+el.borderW > 0) s += 'border:' + L(el.borderW, opts) + ' solid ' + (el.borderColor || '#000') + ';';
        if (+el.radius > 0)  s += 'border-radius:' + L(el.radius, opts) + ';';
        s += 'box-sizing:border-box;';
        return '<div ' + a + ' style="' + baseBox(el, opts, s) + '"></div>';
      }

      case 'photo': {   // employee photo / signature / issuer signature
        var src = el.source === 'signature'   ? entry.__signature
                : el.source === 'issuer_sign' ? entry.__issuer_sign
                : entry.__photo;
        var s2 = 'width:' + L(el.w, opts) + ';height:' + L(el.h, opts) + ';';
        s2 += 'object-fit:' + (el.fit || 'cover') + ';box-sizing:border-box;';
        if (+el.borderW > 0) s2 += 'border:' + L(el.borderW, opts) + ' solid ' + (el.borderColor || '#333') + ';';
        if (+el.radius > 0)  s2 += 'border-radius:' + L(el.radius, opts) + ';';
        if (!src) {
          if (!opts.designer) return '';
          return '<div ' + a + ' style="' + baseBox(el, opts, s2 + 'background:#eee;color:#999;display:flex;align-items:center;justify-content:center;font-size:' + F(6, opts) + ';font-family:Arial;') + '">' + esc(el.source || 'photo') + '</div>';
        }
        return '<img ' + a + ' src="' + esc(src) + '" style="' + baseBox(el, opts, s2) + '">';
      }

      case 'image': {
        if (!el.src) return '';
        var s3 = 'width:' + L(el.w, opts) + ';height:' + L(el.h, opts) + ';';
        if (+el.radius > 0) s3 += 'border-radius:' + L(el.radius, opts) + ';';
        return '<img ' + a + ' src="' + esc(el.src) + '" style="' + baseBox(el, opts, s3) + '">';
      }

      case 'barcode': {
        var bv = codeValue(el, entry);
        var s4 = 'width:' + L(el.w, opts) + ';height:' + L(el.h, opts) + ';overflow:hidden;';
        if (!bv) {
          if (!opts.designer) return '';
          return '<div ' + a + ' style="' + baseBox(el, opts, s4 + 'background:repeating-linear-gradient(90deg,#000 0 2px,#fff 2px 5px);') + '"></div>';
        }
        return '<div ' + a + ' style="' + baseBox(el, opts, s4) + '">'
             + '<svg class="cr-barcode" data-text="' + esc(bv) + '" style="width:100%;height:100%"></svg></div>';
      }

      case 'qr': {
        var qv = codeValue(el, entry);
        var s5 = 'width:' + L(el.w, opts) + ';height:' + L(el.h, opts) + ';overflow:hidden;';
        if (!qv) {
          if (!opts.designer) return '';
          return '<div ' + a + ' style="' + baseBox(el, opts, s5 + 'background:#ddd;') + '"></div>';
        }
        return '<div ' + a + ' class="cr-qr-box" style="' + baseBox(el, opts, s5) + '">'
             + '<div class="cr-qr" data-text="' + esc(qv) + '" style="width:100%;height:100%"></div></div>';
      }
    }
    return '';
  }

  // Full card for one side. Returns the card div HTML.
  function cardHtml(tpl, side, entry, opts, attrFor) {
    var lay  = (tpl.layout && tpl.layout[side]) || { elements: [] };
    var els  = lay.elements || [];
    var s = 'position:relative;overflow:hidden;background:' + (lay.bg || '#fff') + ';'
          + 'width:' + L(tpl.width_mm, opts) + ';height:' + L(tpl.height_mm, opts) + ';';
    var html = '<div class="cr-card" style="' + s + '">';
    for (var i = 0; i < els.length; i++) {
      html += elementHtml(els[i], entry, opts, attrFor ? attrFor(i, els[i]) : '');
    }
    return html + '</div>';
  }

  // After inserting rendered HTML, generate the barcodes/QRs (needs JsBarcode + qrcodejs CDN).
  function renderCodes(root) {
    (root || document).querySelectorAll('svg.cr-barcode').forEach(function (el) {
      try {
        JsBarcode(el, el.getAttribute('data-text'), { format: 'CODE128', displayValue: false, margin: 0 });
        el.removeAttribute('width'); el.removeAttribute('height');
        el.setAttribute('preserveAspectRatio', 'none');
        el.style.width = '100%'; el.style.height = '100%';
      } catch (e) { /* invalid barcode text */ }
    });
    (root || document).querySelectorAll('div.cr-qr').forEach(function (el) {
      if (el.dataset.done) return;
      try {
        el.dataset.done = '1';
        var px = Math.max(el.offsetWidth, el.offsetHeight, 64);
        new QRCode(el, { text: el.getAttribute('data-text'), width: px, height: px, correctLevel: QRCode.CorrectLevel.M });
        var img = el.querySelector('img, canvas');
        if (img) { img.style.width = '100%'; img.style.height = '100%'; }
      } catch (e) { /* qrcodejs unavailable */ }
    });
  }

  global.CardRender = {
    cardHtml: cardHtml,
    elementHtml: elementHtml,
    renderCodes: renderCodes,
    applyTokens: applyTokens,
    PT2MM: PT2MM
  };
})(window);
