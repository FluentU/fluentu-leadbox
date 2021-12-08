(function() {
  var link = document.querySelector('.fluentu-leadbox-link a');
  var modal = document.querySelector('.fluentu-leadbox-backdrop');
  var leadbox = document.querySelector('.fluentu-leadbox');
  var popform = document.querySelector('#fluentu-form');
  var heading = document.querySelector('.fluentu-leadbox h3');
  var btn = document.querySelector('#fluentu-form [type=submit]');
  var close = document.querySelector('.fluentu-leadbox .close-btn');
  var label = btn.value;
  var title = heading.innerHTML;

  close.style.display = 'none';

  popform.addEventListener('submit', function(event) {
    event.preventDefault();

    grecaptcha.ready(function() {
      grecaptcha.execute(options.sitekey, { action: 'submit' }).then(function(token) {
        options.recaptcha_token = token;
        options.email = document.querySelector('#fluentu-form [name=email]').value;

        if (options.email) {
          btn.value = 'Preparing your PDF...';

          var http = new XMLHttpRequest();
          http.open('POST', options.ajaxurl, true);
          http.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
          http.onload = function() {
            var responseText = JSON.parse(http.responseText);
            if (this.status >= 200 && this.status < 400) {
              heading.classList.remove('fluentu-error');
              popform.style.display = 'none';
              close.style.display = 'block';
            } else {
              heading.classList.add('fluentu-error');
            }
            heading.innerHTML = responseText.data;
            btn.value = label;
          };
          http.onerror = function(error) {
            console.error(error);
          };

          delete options.ajaxurl;
          http.send(
            Object.keys(options)
              .map(function(k) {
                return encodeURIComponent(k) + '=' + encodeURIComponent(options[k]);
              })
              .join('&')
          );
        }
      });
    });
  });

  link.addEventListener('click', function(event) {
    event.preventDefault();
    modal.classList.add('fluentu-leadbox-show');
  });

  modal.addEventListener('click', function(event) {
    modal.classList.remove('fluentu-leadbox-show');
    close.style.display = 'none';
    popform.style.display = 'block';
    heading.innerHTML = title;
  });

  close.addEventListener('click', function(event) {
    event.preventDefault();
    modal.classList.remove('fluentu-leadbox-show');
    close.style.display = 'none';
    popform.style.display = 'block';
    heading.innerHTML = title;
  });

  leadbox.addEventListener('click', function(event) {
    event.stopPropagation();
  });
})();
