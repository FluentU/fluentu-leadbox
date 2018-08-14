(function($) {

  const link = $('.fluentu-leadbox-link a');
  const modal = $('.fluentu-leadbox-backdrop');
  const leadbox = $('.fluentu-leadbox');
  const popform = $('#fluentu-form');
  const heading = $('.fluentu-leadbox h3');

  popform.on('submit', function (event) {
    event.preventDefault();
    options.email = $('#fluentu-form [name=email]').val();

    if (options.email) {
      $.post(options.ajaxurl, options, function(response) {
        if (response.success === true) {
          popform.remove();
          heading.html(response.data);
        } else {
          heading.html('Sorry, something went wrong. Please try again');
        }
      });
    }
  });

  link.on('click', function (event) {
    event.preventDefault();
    modal.addClass('fluentu-leadbox-show');
  });

  modal.on('click', function (event) {
    modal.removeClass('fluentu-leadbox-show');
  });

  leadbox.on('click', function (event) {
    event.stopPropagation();
  });

})(jQuery);