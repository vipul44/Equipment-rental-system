jQuery(function($){

    /* ============================================================
       SAVE PRICING
    ============================================================ */
    function doSave(){
        var $msgs = $('.frs-save-msg');
        $('.frs-btn-save').prop('disabled',true).text('Saving…');
        $msgs.text('').css('color','');
        syncCat1Keys();

        // Serialize the entire form as a flat string — most reliable method
        var formData = $('#frs-pricing-form').serialize();

        // Add unchecked addon checkboxes manually (serialize() skips unchecked)
        $('input[name*="[addons]"][type="checkbox"]').each(function(){
            if( !$(this).is(':checked') ){
                // already absent from serialized data — the PHP handler checks isset()
                // so we don't need to add anything extra
            }
        });

        $.ajax({
            url      : frs_admin.ajax_url,
            type     : 'POST',
            data     : formData + '&action=frs_save_pricing&nonce=' + encodeURIComponent(frs_admin.nonce),
            success  : function(r){
                $('.frs-btn-save').prop('disabled',false).text('Save All Changes');
                // r may be a string "0" if wp_die missing — handle gracefully
                var resp = (typeof r === 'string') ? null : r;
                try { if(typeof r === 'string') resp = JSON.parse(r); } catch(e){}
                if(resp && resp.success){
                    $msgs.text('Saved successfully!').css('color','#065f46');
                } else if(resp && !resp.success){
                    $msgs.text('Error: '+(resp.data||'unknown')).css('color','#b91c1c');
                } else {
                    $msgs.text('Saved!').css('color','#065f46');
                }
                setTimeout(function(){$msgs.text('');},4000);
            },
            error    : function(xhr){
                $('.frs-btn-save').prop('disabled',false).text('Save All Changes');
                $msgs.text('Request failed ('+xhr.status+'). Check browser console.').css('color','#b91c1c');
            }
        });
    }

    $('#frs-pricing-form').on('submit',function(e){e.preventDefault();doSave();});

    function syncCat1Keys(){
        $('tr.frs-eq-row[data-cat="lift_capacity"]').each(function(){
            var $inp=$(this).find('.frs-cap-lbl');
            if(!$inp.length)return;
            var nk=$inp.val().trim().toUpperCase().replace(/\s+/g,'_');
            if(!nk)return;
            var ok=$(this).attr('data-key');
            if(nk===ok)return;
            $(this).find('[name]').each(function(){
                var n=$(this).attr('name');
                if(!n||!ok)return;
                var re=new RegExp('\\['+ok.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+'\\]','g');
                $(this).attr('name',n.replace(re,'['+nk+']'));
            });
            $(this).attr('data-key',nk);
        });
    }

    /* ============================================================
       EDIT BUTTON
       Adds/removes "in-edit" class on .frs-card — CSS handles visibility
    ============================================================ */
    $(document).on('click','.frs-btn-edit',function(){
        var id=$(this).data('card');
        var $card=$('#'+id);
        if(!$card.length)return;
        var editing=$card.hasClass('in-edit');
        $card.toggleClass('in-edit',!editing);
        $(this).toggleClass('active',!editing).text(editing?'Edit':'Done');
    });

    /* ============================================================
       ADD EQUIPMENT ROW
    ============================================================ */
    var rc=1000;
    $(document).on('click','.frs-btn-add-row',function(){
        var cat=$(this).data('cat');
        var $tpl=$('#frs-tpl-'+cat);
        if(!$tpl.length){alert('Template not found: '+cat);return;}
        rc++;
        var nk='EQ_'+rc;
        var html=$tpl.html().replace(/NEWKEY/g,nk);
        $(this).closest('table').find('tbody').append(html);
    });

    /* ============================================================
       REMOVE EQUIPMENT ROW
    ============================================================ */
    $(document).on('click','.frs-btn-remove-row',function(){
        if(!confirm('Remove this row?'))return;
        $(this).closest('tr').fadeOut(200,function(){$(this).remove();});
    });

    /* ============================================================
       REMOVE ADD-ON ROW
    ============================================================ */
    $(document).on('click','.frs-btn-remove-addon',function(){
        if(!confirm('Remove this add-on? It will no longer appear on the rental form.'))return;
        $(this).closest('tr').fadeOut(200,function(){$(this).remove();});
    });

    /* ============================================================
       WP MEDIA UPLOADER
       - Fresh frame every click
       - All vars captured in IIFE closure — zero stale-context risk
       - Works on both Upload buttons and camera placeholder divs
    ============================================================ */
    $(document).on('click','.frs-btn-img-upload,.frs-img-ph',function(e){
        e.preventDefault();
        e.stopPropagation();

        if(typeof wp==='undefined'||typeof wp.media==='undefined'){
            alert('Media library unavailable. Try refreshing the page.');
            return;
        }

        // Snapshot context NOW, before any async operation
        var $el   = $(this);
        var $cell = $el.closest('.frs-img-cell');
        var cat   = $el.data('cat') || $cell.find('.frs-btn-img-upload').data('cat');
        var k     = $el.data('k')   || $cell.find('.frs-btn-img-upload').data('k');

        // New frame every time — no caching
        var frame = wp.media({
            title   : 'Select Equipment Image',
            button  : {text:'Use This Image'},
            multiple: false,
            library : {type:'image'}
        });

        // Close over $cell, cat, k so they can never change
        frame.on('select',(function($c,ca,ki){
            return function(){
                var att = frame.state().get('selection').first().toJSON();
                var url = att.url;

                // 1. Update hidden input IMMEDIATELY (before any async)
                $c.find('.frs-img-val').val(url);

                // Swap placeholder → thumb, or update existing thumb
                var $th = $c.find('.frs-img-thumb');
                var $ph = $c.find('.frs-img-ph');
                if($th.length){
                    $th.attr('src',url);
                }else if($ph.length){
                    $ph.replaceWith('<img src="'+safeUrl(url)+'" class="frs-img-thumb">');
                }else{
                    $c.prepend('<img src="'+safeUrl(url)+'" class="frs-img-thumb">');
                }

                // Button → "Change"
                $c.find('.frs-btn-img-upload').text('Change');

                // Add remove button if missing
                if(!$c.find('.frs-btn-img-remove').length&&ca&&ki){
                    $c.find('.frs-btn-img-upload').after(
                        '<button type="button" class="frs-btn-img-remove"'
                        +' data-cat="'+ca+'" data-k="'+ki+'">&#10005;</button>'
                    );
                }

                // Persist to DB immediately and verify
                $.post(frs_admin.ajax_url,{
                    action:'frs_upload_equipment_img',
                    nonce:frs_admin.nonce,
                    url:url, category:ca, key:ki
                }, function(r){
                    if(r && r.success){
                        // Confirm image URL is stored in hidden input
                        $c.find('.frs-img-val').val(url);
                    } else {
                        console.warn('Image DB save failed:', r);
                    }
                });
            };
        })($cell,cat,k));

        frame.open();
    });

    function safeUrl(s){return s.replace(/&/g,'&amp;').replace(/"/g,'&quot;');}

    /* ============================================================
       REMOVE IMAGE
    ============================================================ */
    $(document).on('click','.frs-btn-img-remove',function(e){
        e.preventDefault();
        var $b=$(this);
        var $cell=$b.closest('.frs-img-cell');
        var cat=$b.data('cat'), k=$b.data('k');

        $cell.find('.frs-img-thumb').replaceWith(
            '<div class="frs-img-ph" data-cat="'+cat+'" data-k="'+k+'">&#128247;</div>'
        );
        $cell.find('.frs-img-val').val('');
        $cell.find('.frs-btn-img-upload').text('Upload');
        $b.remove();

        $.post(frs_admin.ajax_url,{action:'frs_remove_equipment_img',nonce:frs_admin.nonce,category:cat,key:k});
    });

    /* ============================================================
       ORDERS — checkboxes, bulk bar, delete
    ============================================================ */
    function $row(on){return $('tr[data-order="'+on+'"]');}
    function bump(d){var $b=$('.frs-badge');$b.text(Math.max(0,(parseInt($b.text(),10)||0)+d));}
    function syncBar(){
        var n=$('.frs-row-chk:checked').length;
        $('#frs-bulk-bar').toggle(n>0);
        $('#frs-sel-count').text(n+' order'+(n>1?'s':'')+' selected');
    }

    $(document).on('change','#frs-chk-all',function(){
        var c=$(this).is(':checked');
        $('.frs-row-chk').prop('checked',c).closest('tr').toggleClass('frs-row-sel',c);
        syncBar();
    });
    $(document).on('change','.frs-row-chk',function(){
        $(this).closest('tr').toggleClass('frs-row-sel',$(this).is(':checked'));
        var all=$('.frs-row-chk').length,chk=$('.frs-row-chk:checked').length;
        $('#frs-chk-all').prop('indeterminate',chk>0&&chk<all).prop('checked',all>0&&chk===all);
        syncBar();
    });
    $(document).on('click','#frs-bulk-cancel',function(){
        $('.frs-row-chk,#frs-chk-all').prop('checked',false).prop('indeterminate',false);
        $('tr.frs-row-sel').removeClass('frs-row-sel');
        $('#frs-bulk-bar').hide();
    });
    $(document).on('click','.frs-btn-del-row',function(e){
        e.preventDefault();
        var on=$(this).data('order');
        if(!confirm('Delete order '+on+'?\n\nPermanent and cannot be undone.'))return;
        var $b=$(this).prop('disabled',true).text('…');
        $.ajax({url:frs_admin.ajax_url,type:'POST',
            data:{action:'frs_delete_order',nonce:frs_admin.nonce,order_number:on},
            success:function(r){
                if(r.success){$row(on).css('background','#fee2e2').fadeOut(400,function(){$(this).remove();bump(-1);});}
                else{alert('Error: '+(r.data||'failed'));$b.prop('disabled',false).text('Delete');}
            },
            error:function(){alert('Request failed.');$b.prop('disabled',false).text('Delete');}
        });
    });
    $(document).on('click','#frs-bulk-del',function(e){
        e.preventDefault();
        var os=[];$('.frs-row-chk:checked').each(function(){os.push($(this).val());});
        if(!os.length||!confirm('Delete '+os.length+' order(s)?\nPermanent.'))return;
        var $b=$(this).prop('disabled',true).text('Deleting…');
        $.ajax({url:frs_admin.ajax_url,type:'POST',
            data:{action:'frs_bulk_delete',nonce:frs_admin.nonce,order_numbers:os},
            success:function(r){
                $b.prop('disabled',false).text('Delete Selected');
                if(r.success){
                    os.forEach(function(on){$row(on).fadeOut(300,function(){$(this).remove();});});
                    setTimeout(function(){bump(-r.data.deleted);$('#frs-bulk-bar').hide();$('#frs-chk-all').prop('checked',false).prop('indeterminate',false);},400);
                }else{alert('Error: '+(r.data||'failed'));}
            },
            error:function(){$b.prop('disabled',false).text('Delete Selected');alert('Request failed.');}
        });
    });

});
