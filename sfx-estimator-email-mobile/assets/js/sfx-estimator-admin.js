
(function($){
  // Tile images
  $(document).on('click', '.sfx-media-select', function(e){
    e.preventDefault();
    const target = $('#' + $(this).data('target'));
    const frame = wp.media({title:'Select image', button:{text:'Use image'}, library:{type:'image'}, multiple:false});
    frame.on('select', function(){
      const att = frame.state().get('selection').first().toJSON();
      target.val(att.id);
      const img = target.closest('td').find('img');
      img.attr('src', (att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url) );
    });
    frame.open();
  });
  $(document).on('click', '.sfx-media-clear', function(e){
    e.preventDefault();
    const target = $('#' + $(this).data('target'));
    target.val('');
    const img = target.closest('td').find('img');
    img.attr('src', SFXEstimatorAdmin.placeholder || img.attr('src'));
  });

  // Brand images
  $(document).on('click', '#sfx-add-make-row', function(e){
    e.preventDefault();
    const row = `<tr>
      <td><input type="text" name="make_type[]" value="" class="regular-text" list="sfx-type-list"/></td>
      <td><input type="text" name="make_label[]" value="" class="regular-text"/></td>
      <td>
        <div style="display:flex;align-items:center;gap:12px;">
          <img src="${SFXEstimatorAdmin.placeholder}" style="width:48px;height:48px;object-fit:contain;background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:4px;">
          <input type="hidden" name="make_image[]" value="0"/>
          <button type="button" class="button sfx-media-select-make">Select Image</button>
          <button type="button" class="button sfx-make-remove">Remove</button>
        </div>
      </td>
    </tr>`;
    $('#sfx-make-table').append(row);
  });
  $(document).on('click', '.sfx-make-remove', function(e){
    e.preventDefault();
    $(this).closest('tr').remove();
  });
  $(document).on('click', '.sfx-media-select-make', function(e){
    e.preventDefault();
    const hidden = $(this).closest('td').find('input[type=hidden]');
    const img = $(this).closest('td').find('img');
    const frame = wp.media({title:'Select brand image', button:{text:'Use image'}, library:{type:'image'}, multiple:false});
    frame.on('select', function(){
      const att = frame.state().get('selection').first().toJSON();
      hidden.val(att.id);
      img.attr('src', (att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url) );
    });
    frame.open();
  });

  // Series images
  $(document).on('click', '#sfx-add-series-row', function(e){
    e.preventDefault();
    const row = `<tr>
      <td><input type="text" name="series_label[]" value="" class="regular-text"/></td>
      <td>
        <div style="display:flex;align-items:center;gap:12px;">
          <img src="${SFXEstimatorAdmin.placeholder}" style="width:48px;height:48px;object-fit:contain;background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:4px;">
          <input type="hidden" name="series_image[]" value="0"/>
          <button type="button" class="button sfx-media-select-series">Select Image</button>
          <button type="button" class="button sfx-series-remove">Remove</button>
        </div>
      </td>
    </tr>`;
    $('#sfx-series-table').append(row);
  });
  $(document).on('click', '.sfx-series-remove', function(e){
    e.preventDefault();
    $(this).closest('tr').remove();
  });
  $(document).on('click', '.sfx-media-select-series', function(e){
    e.preventDefault();
    const hidden = $(this).closest('td').find('input[type=hidden]');
    const img = $(this).closest('td').find('img');
    const frame = wp.media({title:'Select series image', button:{text:'Use image'}, library:{type:'image'}, multiple:false});
    frame.on('select', function(){
      const att = frame.state().get('selection').first().toJSON();
      hidden.val(att.id);
      img.attr('src', (att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url) );
    });
    frame.open();
  });

  // Model images
  $(document).on('click', '#sfx-add-model-row', function(e){
    e.preventDefault();
    const row = `<tr>
      <td><input type="text" name="model_label[]" value="" class="regular-text"/></td>
      <td>
        <div style="display:flex;align-items:center;gap:12px;">
          <img src="${SFXEstimatorAdmin.placeholder}" style="width:48px;height:48px;object-fit:contain;background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:4px;">
          <input type="hidden" name="model_image[]" value="0"/>
          <button type="button" class="button sfx-media-select-model">Select Image</button>
          <button type="button" class="button sfx-model-remove">Remove</button>
        </div>
      </td>
    </tr>`;
    $('#sfx-model-table').append(row);
  });
  $(document).on('click', '.sfx-model-remove', function(e){
    e.preventDefault();
    $(this).closest('tr').remove();
  });
  $(document).on('click', '.sfx-media-select-model', function(e){
    e.preventDefault();
    const hidden = $(this).closest('td').find('input[type=hidden]');
    const img = $(this).closest('td').find('img');
    const frame = wp.media({title:'Select model image', button:{text:'Use image'}, library:{type:'image'}, multiple:false});
    frame.on('select', function(){
      const att = frame.state().get('selection').first().toJSON();
      hidden.val(att.id);
      img.attr('src', (att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url) );
    });
    frame.open();
  });
})(jQuery);
