$(document).ready(function () {

  /***************** Waypoints — animações ao scroll ******************/

  $('.wp1').waypoint(function () {
    $('.wp1').addClass('animated fadeInLeft');
  }, { offset: '75%' });

  $('.wp2').waypoint(function () {
    $('.wp2').addClass('animated fadeInDown');
  }, { offset: '75%' });

  $('.wp3').waypoint(function () {
    $('.wp3').addClass('animated bounceInDown');
  }, { offset: '75%' });

  $('.wp4').waypoint(function () {
    $('.wp4').addClass('animated fadeInDown');
  }, { offset: '75%' });

  /***************** Flickity — slider ******************/

  $('#featuresSlider').flickity({
    cellAlign: 'left',
    contain: true,
    prevNextButtons: false
  });

  /***************** Nav — menu overlay ******************/

  $('.nav-toggle').click(function (e) {
    e.preventDefault();
    $(this).toggleClass('active');
    $('.overlay-boxify').toggleClass('open');
  });

  $('.overlay ul li a').click(function (e) {
    e.preventDefault();
    e.stopPropagation();
    var hash = this.getAttribute('href');
    $('.nav-toggle').removeClass('active');
    $('.overlay-boxify').removeClass('open');
    // aguarda o fecho do overlay antes de fazer scroll
    setTimeout(function () {
      var target = document.querySelector(hash);
      if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }, 550);
  });

  $('.overlay').click(function () {
    $('.nav-toggle').removeClass('active');
    $('.overlay-boxify').removeClass('open');
  });

  /***************** Smooth Scrolling — força scroll ao topo do elemento ******************/

  $(document).on('click', 'a[href^="#"]', function (e) {
    var hash = this.getAttribute('href');
    if (!hash || hash === '#') return;
    var target = document.querySelector(hash);
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });

  /***************** Formulário de contacto ******************/

  $('#contactForm').on('submit', function (e) {
    e.preventDefault();

    var $btn      = $('#cf-submit');
    var $feedback = $('#cf-feedback');
    var nome      = $('#cf-nome').val().trim();
    var email     = $('#cf-email').val().trim();
    var mensagem  = $('#cf-mensagem').val().trim();

    $feedback.removeClass('cf-success cf-error').text('');

    if (!nome || !email || !mensagem) {
      $feedback.addClass('cf-error').text('Por favor preencha os campos obrigatórios.');
      return;
    }

    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> A enviar…');

    $.ajax({
      url: 'contact.php',
      method: 'POST',
      data: $(this).serialize(),
      success: function (res) {
        try { res = typeof res === 'string' ? JSON.parse(res) : res; } catch(e) {}
        if (res.success) {
          $feedback.addClass('cf-success').text('Mensagem enviada! Entraremos em contacto brevemente.');
          $('#contactForm')[0].reset();
        } else {
          $feedback.addClass('cf-error').text(res.error || 'Erro ao enviar. Tente novamente.');
        }
      },
      error: function () {
        $feedback.addClass('cf-error').text('Erro de ligação. Tente novamente.');
      },
      complete: function () {
        $btn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Enviar mensagem');
      }
    });
  });

});
