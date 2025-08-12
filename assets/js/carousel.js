$(".js-home-products").owlCarousel({
  loop: false,
  margin: 10,
  nav: true,
  autoplay: true,
  autoplayTimeout: 3000,
  responsive: {
    0: { items: 2 },
    600: { items: 2 },
    1000: { items: 5 },
  },
});
