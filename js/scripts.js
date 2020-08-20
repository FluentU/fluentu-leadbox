(function($) {
  const link = $('.fluentu-leadbox-link a');
  const modal = $('.fluentu-leadbox-backdrop');
  const leadbox = $('.fluentu-leadbox');
  const popform = $('#fluentu-form');
  const heading = $('.fluentu-leadbox h3');
  const btn = $('#fluentu-form [type=submit]');
  const close = $('.fluentu-leadbox .close-btn');
  const label = btn.val();
  const title = heading.html();

  close.hide();

  popform.on('submit', function(event) {
    event.preventDefault();
    options.email = $('#fluentu-form [name=email]').val();

    if (options.email) {
      $(btn).val('Preparing your PDF...');
      $.post(options.ajaxurl, options, function(response) {
        if (response.success === true) {
          popform.hide();
          close.show();
        }
        heading.html(response.data);
        $(btn).val(label);
      });
    }
  });

  link.on('click', function(event) {
    event.preventDefault();
    modal.addClass('fluentu-leadbox-show');
  });

  modal.on('click', function(event) {
    modal.removeClass('fluentu-leadbox-show');
    close.hide();
    popform.show();
    heading.html(title);
  });

  close.on('click', function(event) {
    event.preventDefault();
    modal.removeClass('fluentu-leadbox-show');
    close.hide();
    popform.show();
    heading.html(title);
  });

  leadbox.on('click', function(event) {
    event.stopPropagation();
  });
})(jQuery);
