/*
 * LANGA Tools Client — Admin UI
 */
(function(){
  'use strict';
  document.addEventListener('DOMContentLoaded', function(){
    // Validate required email fields on form submit
    document.querySelectorAll('form').forEach(function(form){
      form.addEventListener('submit', function(e){
        var fields = form.querySelectorAll('.langa-required-email');
        var ok = true;
        fields.forEach(function(f){
          var v = f.value.trim();
          f.style.borderColor = '';
          f.style.boxShadow = '';
          if (!v) {
            f.style.borderColor = '#d63638';
            f.style.boxShadow = '0 0 0 1px #d63638';
            f.focus();
            ok = false;
          }
        });
        if (!ok) {
          e.preventDefault();
          var first = form.querySelector('.langa-required-email[style*="border-color"]');
          if (first) {
            first.scrollIntoView({behavior:'smooth', block:'center'});
            // Show alert
            var msg = first.closest('td') || first.parentElement;
            if (msg && !msg.querySelector('.langa-email-error')) {
              var err = document.createElement('p');
              err.className = 'langa-email-error';
              err.style.cssText = 'color:#d63638;font-size:12px;font-weight:600;margin:6px 0 0';
              err.textContent = 'Email recipient is required. Please enter at least one email address.';
              msg.appendChild(err);
              // Remove after 5s
              setTimeout(function(){ if (err.parentNode) err.parentNode.removeChild(err); }, 5000);
            }
          }
        }
      });
    });
    // Clear red border on input
    document.querySelectorAll('.langa-required-email').forEach(function(f){
      f.addEventListener('input', function(){
        f.style.borderColor = '';
        f.style.boxShadow = '';
        var err = (f.closest('td') || f.parentElement).querySelector('.langa-email-error');
        if (err) err.parentNode.removeChild(err);
      });
    });
  });
})();
