/***** SCRIPT PERSONALIZZZATO *****/

$(document).ready(function() {
  // forza chiamata POST per link
  $('.gs-post-link').click(postLink);
});

// forza chiamata POST per link
function postLink(e) {
  var el = $(this);
  var url = el.attr('href') || el.attr('data-href');
  if (!url || url === '#') {
    return;
  }
  e.preventDefault();
  // crea il form e l'input CSRF al volo, poi esegue il submit
  $('<form>', {
    action: url,
    method: 'POST',
    style: 'display: none;'
  }).append($('<input>', {
    type: 'hidden',
    name: '_csrf_token',
    value: el.attr('data-csrf') || ''
  })).appendTo('body').submit();
}
