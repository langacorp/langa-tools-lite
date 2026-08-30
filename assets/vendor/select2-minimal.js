/**
 * Minimal Select2-compatible multi-select widget
 * For LANGA Tools credits form only
 * Provides: jQuery.fn.select2() with data(), destroy, open
 * License: MIT
 * @version 1.0.0
 */
(function($){
  if (!$ || $.fn.select2) return;

  var defaults = {
    placeholder: '',
    maximumSelectionLength: 0,
    templateSelection: null,
    language: { maximumSelected: function(e){ return 'Max '+e.maximum+' items'; } }
  };

  function S2(el, opts) {
    this.$el = $(el);
    this.opts = $.extend({}, defaults, opts);
    this.items = [];
    this._build();
  }

  S2.prototype = {
    _build: function() {
      var self = this;
      this.$el.hide();
      this.$wrap = $('<span class="select2-container select2-container--default" style="width:100%!important"></span>');
      this.$sel = $('<span class="select2-selection select2-selection--multiple" style="min-height:36px;display:flex;flex-wrap:wrap;align-items:center;gap:4px;padding:4px;border:1px solid #ddd;border-radius:4px;cursor:text;background:#fff"></span>');
      this.$rendered = $('<ul class="select2-selection__rendered" style="list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:4px;width:100%"></ul>');
      this.$search = $('<input type="search" class="select2-search__field" style="border:none;outline:none;flex:1;min-width:60px;font-size:13px;padding:2px 4px;background:transparent" />');
      this.$dd = $('<div class="select2-dropdown" style="position:absolute;z-index:99999;background:#fff;border:1px solid #ddd;border-radius:0 0 4px 4px;max-height:200px;overflow-y:auto;display:none;width:100%"></div>');
      this.$results = $('<ul class="select2-results__options" style="list-style:none;margin:0;padding:0"></ul>');

      this.$rendered.append($('<li style="flex:1;min-width:60px"></li>').append(this.$search));
      this.$sel.append(this.$rendered);
      this.$dd.append(this.$results);
      this.$wrap.append(this.$sel).append(this.$dd);
      this.$el.after(this.$wrap);

      if (this.opts.placeholder) this.$search.attr('placeholder', this.opts.placeholder);

      // Build options list from <select>
      this._options = [];
      this.$el.find('option').each(function(){
        self._options.push({ id: $(this).val(), text: $(this).text(), el: this });
      });

      // Pre-selected
      this.$el.find('option:selected').each(function(){
        self._addTag($(this).val(), $(this).text());
      });

      this.$search.on('focus', function(){ self._open(); });
      this.$search.on('input', function(){ self._filter(this.value); });
      this.$sel.on('click', function(){ self.$search.focus(); });

      $(document).on('click.s2_'+this.$el.attr('id'), function(e){
        if (!$(e.target).closest(self.$wrap).length) self._close();
      });

      this._render();
    },

    _open: function() {
      this._filter(this.$search.val());
      this.$dd.show();
    },

    _close: function() {
      this.$dd.hide();
    },

    _filter: function(q) {
      var self = this, sel = this.items.map(function(i){ return i.id; });
      q = (q||'').toLowerCase();
      this.$results.empty();
      var count = 0;
      $.each(this._options, function(i, opt){
        if (sel.indexOf(opt.id) !== -1) return;
        if (q && opt.text.toLowerCase().indexOf(q) === -1) return;
        var $li = $('<li class="select2-results__option select2-results__option--selectable" style="padding:6px 10px;cursor:pointer;font-size:13px"></li>').text(opt.text).data('id', opt.id);
        $li.on('mousedown', function(e){
          e.preventDefault();
          self._addTag(opt.id, opt.text);
          self.$search.val('');
          self._filter('');
        });
        $li.on('mouseenter', function(){ $(this).css('background','#f0f0f0'); });
        $li.on('mouseleave', function(){ $(this).css('background',''); });
        self.$results.append($li);
        count++;
      });
      if (!count) {
        this.$results.append('<li style="padding:6px 10px;color:#999;font-size:13px">No results</li>');
      }
    },

    _addTag: function(id, text) {
      if (this.opts.maximumSelectionLength && this.items.length >= this.opts.maximumSelectionLength) return;
      var self = this;
      this.items.push({ id: id, text: text });
      this.$el.find('option[value="'+id+'"]').prop('selected', true);
      this.$el.trigger('change');
      this._renderTags();
    },

    _removeTag: function(id) {
      this.items = this.items.filter(function(i){ return i.id !== id; });
      this.$el.find('option[value="'+id+'"]').prop('selected', false);
      this.$el.trigger('change');
      this._renderTags();
      this._filter(this.$search.val());
    },

    _renderTags: function() {
      var self = this;
      this.$rendered.find('.select2-selection__choice').remove();
      $.each(this.items, function(i, item){
        var label = item.text;
        if (self.opts.templateSelection) {
          var r = self.opts.templateSelection(item);
          if (typeof r === 'string') label = r;
          else if (r && r.text) label = r.text;
        }
        var $tag = $('<li class="select2-selection__choice" style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;background:#f0f0f0;border-radius:3px;font-size:12px;white-space:nowrap"></li>');
        var $rm = $('<span class="select2-selection__choice__remove" style="cursor:pointer;font-weight:bold;color:#999;padding-right:4px" aria-label="Remove">&times;</span>');
        $rm.on('mousedown', function(e){ e.preventDefault(); e.stopPropagation(); self._removeTag(item.id); });
        $tag.append($rm).append(document.createTextNode(label));
        self.$rendered.find('li:last').before($tag);
      });
    },

    _render: function() {
      this._renderTags();
    },

    data: function() {
      return this.items.slice();
    },

    destroy: function() {
      this.$wrap.remove();
      this.$el.show();
      $(document).off('.s2_'+this.$el.attr('id'));
    }
  };

  $.fn.select2 = function(opts) {
    if (opts === 'data') {
      var inst = this.data('s2inst');
      return inst ? inst.data() : [];
    }
    if (opts === 'destroy') {
      return this.each(function(){ var i=$(this).data('s2inst'); if(i)i.destroy(); });
    }
    return this.each(function(){
      var $t = $(this);
      if ($t.data('s2inst')) return;
      var inst = new S2(this, opts || {});
      $t.data('s2inst', inst);
    });
  };
})(window.jQuery);
