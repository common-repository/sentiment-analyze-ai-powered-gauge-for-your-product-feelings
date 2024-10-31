jQuery(document).ready(function($) {
    $('.send-email-button').on('click', function(e) {
        e.preventDefault(); // Form gönderimini engelleyin
        var comment_id = $(this).data('comment-id');
        var nonce = $('#_wpnonce').val();
        
        var data = {
            'action': 'send_response_email',
            'comment_id': comment_id,
            'security': nonce
        };
        
        // AJAX çağrısını başlatın
        $.post(ajaxurl, data, function(response) {
            alert(response); // Yanıtı bir uyarı olarak gösterin
        });
    });

    // Ürün arama input'unuzun seçicisini kullanın, örneğin #product_search
    $('#product_search').autocomplete({
        source: ajaxurl + '?action=get_products_by_search',
        minLength: 3, // Kullanıcının otomatik tamamlama sonuçlarını görmek için yazması gereken minimum karakter sayısı
        select: function(event, ui) {
            $('#selected_product_id').val(ui.item.id);
        }
    });

    // Kullanıcı arama otomatik tamamlama
    $('#user_search').autocomplete({
        source: function(request, response) {
            $.ajax({
                url: ajaxurl,
                dataType: "json",
                data: {
                    action: 'get_users_by_search',
                    term: request.term // Kullanıcı tarafından girilen arama terimi
                },
                success: function(data) {
                    // Gelen veriyi işleyip formatlayın
                    response($.map(data, function(item) {
                        return {
                            label: item.user_login + ' - ' + item.user_email, // Görüntülenecek metin
                            value: item.user_login // Gerçek değer, bu örnekte kullanıcı adı
                        };
                    }));
                }
            });
        },
        minLength: 3,
        select: function(event, ui) {
            // Kullanıcı seçildiğinde, gizli form alanına kullanıcı adını ekleyin
            $('#selected_user').val(ui.item.value);
        }
    });
});
