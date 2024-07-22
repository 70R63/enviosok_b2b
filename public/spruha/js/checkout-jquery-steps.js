  var $sections = $('.form-section');

  function navigateTo(index) {
    // Mark the current section with the class 'current'
    $sections
        .removeClass('current')
        .eq(index)
        .addClass('current');
    // Show only the navigation buttons that make sense for the current section:
    $('.form-navigation .previous').toggle(index > 0);
    var atTheEnd = index >= $sections.length - 1;
    $('.form-navigation .next').toggle(!atTheEnd);
    $('.form-navigation [type=submit]').toggle(atTheEnd);
  }

  function curIndex() {
    // Return the current index by looking at which section has the class 'current'
    return $sections.index($sections.filter('.current'));
  }

  // Previous button is easy, just go back
  $('.form-navigation .previous').click(function () {
    console.log('click previous');
    navigateTo(curIndex() - 1);
  });

  // Next button goes forward iff current block validates
  $('.form-navigation .next').click(function () {
    console.log('click next');
    if ($('.demo-form').parsley().validate({group: 'block-' + curIndex()})) {
      console.log('section is valid moved to next');
      navigateTo(curIndex() + 1);
    } else {
      console.log('section is not valid')
    }

  });

  // Prepare sections by setting the `data-parsley-group` attribute to 'block-0', 'block-1', etc.
  $sections.each(function (index, section) {
    $(section).find(':input').attr('data-parsley-group', 'block-' + index);
  });
  navigateTo(0); // Start at the beginning


  // hook form submit event
  $(".demo-form").on('submit', function (e) {
    e.preventDefault();
    console.log('form submit');

    var instance = $(this).parsley();

    alert('form status = ', instance.isValid());

    return false;
  });