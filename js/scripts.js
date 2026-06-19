/***************** i18n — internacionalização ******************/

function applyLang(lang) {
  var t = translations[lang];
  if (!t) return;

  // Textos normais
  document.querySelectorAll('[data-i18n]').forEach(function(el) {
    var key = el.getAttribute('data-i18n');
    if (t[key] !== undefined) el.textContent = t[key];
  });

  // Placeholders
  document.querySelectorAll('[data-i18n-placeholder]').forEach(function(el) {
    var key = el.getAttribute('data-i18n-placeholder');
    if (t[key] !== undefined) el.setAttribute('placeholder', t[key]);
  });

  // Atualizar html lang e botão
  document.documentElement.lang = lang;
  var btn = document.getElementById('lang-switcher');
  if (btn) btn.textContent = lang === 'pt' ? '🇬🇧 EN' : '🇵🇹 PT';

  localStorage.setItem('hncn-lang', lang);
}

function detectLang() {
  var saved = localStorage.getItem('hncn-lang');
  if (saved) return saved;
  var browser = (navigator.language || navigator.userLanguage || 'pt').toLowerCase();
  return browser.startsWith('en') ? 'en' : 'pt';
}

document.addEventListener('DOMContentLoaded', function() {
  var lang = detectLang();
  applyLang(lang);

  document.getElementById('lang-switcher').addEventListener('click', function() {
    var current = document.documentElement.lang || 'pt';
    applyLang(current === 'pt' ? 'en' : 'pt');
  });
});

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

  /***************** Galeria de portfólio ******************/

  var galleryData = [];
  var currentAlbum = 0;
  var currentPhoto = 0;
  var carouselOffset = 0;

  function getVisibleCount() {
    var w = $(window).width();
    if (w < 576) return 1;
    if (w < 992) return 2;
    return 4;
  }

  function renderTabs() {
    var tabs = $('#gallery-tabs');
    tabs.empty();
    galleryData.forEach(function(album, i) {
      var btn = $('<button class="gallery-tab">')
        .text(album.name + ' (' + album.count + ')')
        .on('click', function() {
          currentAlbum = i;
          currentPhoto = 0;
          $('.gallery-tab').removeClass('active');
          $(this).addClass('active');
          renderCarousel();
        });
      if (i === 0) btn.addClass('active');
      tabs.append(btn);
    });
  }

  function renderCarousel() {
    var grid = $('#gallery-grid');
    grid.empty();
    carouselOffset = 0;

    var photos = galleryData[currentAlbum].photos;
    var total  = photos.length;

    // Setas no topo
    var nav   = $('<div class="gallery-nav">');
    var prevBtn = $('<button class="gallery-nav-btn" id="carousel-prev">').html('&#8249;');
    var nextBtn = $('<button class="gallery-nav-btn" id="carousel-next">').html('&#8250;');
    nav.append(prevBtn).append(nextBtn);

    // Track
    var trackWrap = $('<div class="carousel-track-wrap">');
    var track     = $('<div class="carousel-track" id="gallery-carousel-track">');

    photos.forEach(function(photo, i) {
      var caption = photo.caption || '';
      var card = $('<div class="gallery-card">')
        .on('click', function() { openLightbox(i); });

      var inner = $('<div class="gallery-card-inner">');
      inner.append(
        $('<img class="gallery-card-img">').attr({ src: photo.thumb, alt: caption, loading: 'lazy' })
      );
      var body = $('<div class="gallery-card-body">');
      if (caption) {
        body.append($('<p class="gallery-card-caption">').text(caption));
      }
      body.append($('<span class="gallery-card-num">').text((i + 1) + ' / ' + total));
      inner.append(body);
      card.append(inner);
      track.append(card);
    });

    prevBtn.on('click', function() { carouselOffset--; updateCarousel(); });
    nextBtn.on('click', function() { carouselOffset++; updateCarousel(); });

    trackWrap.append(track);
    grid.append(nav).append(trackWrap);

    setTimeout(function() { updateCarousel(); }, 50);
  }

  function updateCarousel() {
    var photos  = galleryData[currentAlbum].photos;
    var visible = getVisibleCount();
    var max     = Math.max(0, photos.length - visible);
    carouselOffset = Math.min(Math.max(0, carouselOffset), max);

    var slideW = Math.floor($('.carousel-track-wrap').width() / visible);

    $('.gallery-card').css({ 'width': slideW + 'px', 'flex': '0 0 ' + slideW + 'px' });
    $('#gallery-carousel-track').css('transform', 'translateX(-' + (slideW * carouselOffset) + 'px)');
    $('#carousel-prev').prop('disabled', carouselOffset === 0);
    $('#carousel-next').prop('disabled', carouselOffset >= max);
  }

  $(window).on('resize', function() {
    if (galleryData.length > 0) updateCarousel();
  });

  function openLightbox(index) {
    currentPhoto = index;
    var photo = galleryData[currentAlbum].photos[currentPhoto];
    $('#lb-img').attr('src', photo.src).attr('alt', photo.caption);
    $('#lb-caption').text(photo.caption);
    $('#gallery-lightbox').addClass('open');
    $('body').css('overflow', 'hidden');
  }

  function closeLightbox() {
    $('#gallery-lightbox').removeClass('open');
    $('body').css('overflow', '');
  }

  function lbNext() {
    var photos = galleryData[currentAlbum].photos;
    currentPhoto = (currentPhoto + 1) % photos.length;
    var photo = photos[currentPhoto];
    $('#lb-img').attr('src', photo.src).attr('alt', photo.caption);
    $('#lb-caption').text(photo.caption);
  }

  function lbPrev() {
    var photos = galleryData[currentAlbum].photos;
    currentPhoto = (currentPhoto - 1 + photos.length) % photos.length;
    var photo = photos[currentPhoto];
    $('#lb-img').attr('src', photo.src).attr('alt', photo.caption);
    $('#lb-caption').text(photo.caption);
  }

  $('#lb-close').on('click', closeLightbox);
  $('#lb-next').on('click', lbNext);
  $('#lb-prev').on('click', lbPrev);
  $('#gallery-lightbox').on('click', function(e) {
    if ($(e.target).is('#gallery-lightbox')) closeLightbox();
  });
  $(document).on('keydown', function(e) {
    if (!$('#gallery-lightbox').hasClass('open')) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowRight') lbNext();
    if (e.key === 'ArrowLeft') lbPrev();
  });

  // Carregar galeria
  $.getJSON('gallery.php', function(data) {
    $('#gallery-loading').hide();
    if (!data || data.error || data.length === 0) {
      $('#gallery-grid').html('<p style="color:#999;padding:20px">Sem álbuns disponíveis.</p>');
      return;
    }
    galleryData = data;
    renderTabs();
    renderCarousel();
  }).fail(function() {
    $('#gallery-loading').hide();
    $('#gallery-grid').html('<p style="color:#999;padding:20px">Não foi possível carregar o portfólio.</p>');
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
