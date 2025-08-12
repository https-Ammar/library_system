function loadCart() {
  $.ajax({
    type: "GET",
    url: "show_cart.php",
    success: (response) => $("#offcanvasCart").html(response),
    error: (xhr, status, error) => console.error("AJAX Error:", status, error),
  });
}

function addcart(productid) {
  var quantity = $(".quantity" + productid).val();
  $.ajax({
    type: "POST",
    url: "add_cart.php",
    data: { productid, qty: quantity },
    success: () => loadCart(),
    error: (xhr, status, error) => console.error("AJAX Error:", status, error),
  });
}

function addmoreone(id) {
  $.ajax({
    type: "POST",
    url: "add_more_one.php",
    data: { id },
    success: () => loadCart(),
    error: (xhr, status, error) => console.error("AJAX Error:", status, error),
  });
}

function removemoreone(id) {
  $.ajax({
    type: "POST",
    url: "remove_more_one.php",
    data: { id },
    success: () => loadCart(),
    error: (xhr, status, error) => console.error("AJAX Error:", status, error),
  });
}

function removecart(id) {
  $.ajax({
    type: "POST",
    url: "remove_cart.php",
    data: { id },
    success: () => loadCart(),
    error: (xhr, status, error) => console.error("AJAX Error:", status, error),
  });
}

window.addEventListener("DOMContentLoaded", loadCart);
