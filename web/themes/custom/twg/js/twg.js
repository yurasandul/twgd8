(function ($, Drupal) {
  var bodyMinimum = 320,
    desktopMinimum = 700,
    desktopMaximum = 1024,
    menuHeight = 82,
    stickyMenuHeight = 66,
    mobileMenuHeight = 50;

  Drupal.behaviors.mainTWG = {
    attach: function(context, settings) {

      $(".search-opener").once('searchToggler').click(function (e) {
        $('.search-block-form').toggleClass('open');
        $('#block-main-navigation-mobile').removeClass('open');
        $(".search-opener").toggleClass('open');
      });

      $(".mobile-menu-opener").once('mobileMenuOpener').click(function (e) {
        $('#block-main-navigation-mobile').toggleClass('open');
          $('.search-block-form').removeClass('open');
      });

      // First level items that have a submenu most be able to open on mobile
      $('#block-main-navigation-mobile .menu li a').click(function(e) {
        var parent = $(this).parent();
        if(!parent.hasClass('is-leaf')) {
          // This items has a submenu, toggle the display of it instead of navigating to the page
          parent.toggleClass('open');
          parent.find('.menu').toggle();
          e.preventDefault();
          return false;
        }
        return true;
      });

      // On tweet pages, references need to be able to fold out using JS
      $('.node--type-tweet-page .field--name-field-reference-body').once('makeBodyTeaser').each(function() {
        $(this).addClass('closed');
        var originalFirstParagraph = $(this).find('p:first-child').text();
        var previewEl = $('<p class="preview"></p>').text(originalFirstParagraph.substring(0, 100));
        $(this).prepend(previewEl);
      });

      $('.node--type-tweet-page .field--name-field-reference-body').once('makeBodyToggle').click(function() {
        $(this).toggleClass('closed');
      });

      $('.node--type-tweet-page .field-name-field-reference-heading').once('makeHeaderToggle').click(function() {
        $(this).siblings('.field--name-field-reference-body').toggleClass('closed');
      });




    }
  };

  Drupal.behaviors.sticky_header = {
    showMenuAfterPixels: 8,

    attach: function(context, settings) {
      var self = this;
      $(window).scroll(function() {
        var scrolledY = $(window).scrollTop();
        var pageObj = $('#page');

        var showAfter = self.showMenuAfterPixels;

        // If we are on small, we have a different point from where we stick the header
        if($(window).innerWidth() < desktopMinimum) showAfter = 0;

        if(scrolledY >= showAfter && !pageObj.hasClass('sticky')) {
          // Scrolled anything, so make menu sticky
          pageObj.addClass('sticky');
        }
        else if(scrolledY < showAfter && pageObj.hasClass('sticky')) {
          // Return menu to static
          pageObj.removeClass('sticky');
        }
      });
    }
  };

  Drupal.behaviors.live_search = {
    data: {},
    dataCounter: 0,
    itemSelector: '.live-page-search a',
    inputElement: '#live-page-input',
    postProcessContainer: '.view-twg-all-tweets .view-content',

    attach: function(context, settings) {
      var $input = $(this.inputElement);
      if($input.length > 0) {
        // Active live search
        this.index();
        this.attachListener($input);

        $('.live-page-search--form').submit(function(e) {
          e.preventDefault();
          return false;
        })
      }
    },

    index: function() {
      var self = this;
      var counter = 0;
      $(this.itemSelector).each(function() {
        // Cache string and set an ID for the element
        var id = 'live-search-el-' + counter;
        self.addData(id, $(this).text().trim());
        $(this).attr('id', id);
        counter++;
      });
    },

    attachListener: function(toInput) {
      var self = this;
      toInput.keyup(function(e) {
        var searchStr = $(this).val().trim();
        var results = self.findActive(searchStr);
        self.showResults(results, searchStr);
      });
    },

    addData: function(id, str) {
      this.data[id] = str;
      this.dataCounter++;
    },

    findActive: function(searchStr) {
      if(this.dataCounter == 0) return [];
      var result = [];
      for(var k in this.data) {
        var regex = new RegExp(searchStr, 'i');
        if(regex.test(this.data[k])) {
          result[result.length] = k;
        }
      }
      return result;
    },

    showResults: function(resultIds, searchStr) {
      var self = this;

      // Show heading
      $(this.postProcessContainer + ' h3').show();

      // Hide all results
      $(this.itemSelector).removeClass('found').hide();

      var regex = new RegExp('(' + searchStr + ')', 'gi');
      for(var i= 0, j=resultIds.length; i<j; i++) {
        var currentId = resultIds[i];
        var original = self.data[currentId];
        $('#' + currentId).html(original.replace(regex, '<span class="h">$1</span>')).addClass('found').show();
      }

      // Post parse the results to show/hide the headings
      this.postParseResult();
    },

    // Parse the result container to hide heading of parts we don't have any results in
    postParseResult: function() {
      var allKids = $(this.postProcessContainer).children();

      var currentHeading = null;
      var elementsHidden = true;
      allKids.each(function() {
        var current = $(this);
        var tagName = current.prop('tagName').toLowerCase();
        if(tagName == 'h3') {
          if(currentHeading != null) {
            $(currentHeading).toggle(!elementsHidden);
            elementsHidden = true;
          }
          currentHeading = current;
        }
        else if(tagName == 'div' && current.find('.found').length > 0) {
          elementsHidden = false;
        }
      });

      if(currentHeading != null) {
        $(currentHeading).toggle(!elementsHidden);
      }
    }
  };

  Drupal.behaviors.sticky_subheader = {
    stickySelector: '.turns-sticky',
    dummyDataAttribute: 'data-stickydummyid',
    originalOffsetAttribute: 'data-originalOffset',

    attach: function(context, settings) {
      var self = this;

      // Apparantly there is no real method of attaching something to the
      // window.load event using Drupal Javascript behaviors.
      // I'll just do it inside the attach function (which is basically a window.ready() handler.
      //$(window).load(function() {

        var qwe = $(self.stickySelector);

        // calculate initial offset for elements that stick to the header.
        // So we know when to make them sticky and when to make them static again.
        $(self.stickySelector).each(function() {
          var current = $(this);
          var dummy = $('#' + $(this).attr(self.dummyDataAttribute));
          var originalOffset = current.offset().top;
          current.attr(self.originalOffsetAttribute, originalOffset);
          dummy.css('height', current.height());
          dummy.css('margin-top', current.css('margin-top'));
          dummy.css('margin-bottom', current.css('margin-bottom'));
        });

        $(window).scroll(self, self.scrollHandler);
      //});
    },

    scrollHandler: function(obj) {
      var self = obj.data;
      $(self.stickySelector).each(function() {
        var current = $(this);
        var dummy = $('#' + $(this).attr(self.dummyDataAttribute));
        var originalOffset = current.attr(self.originalOffsetAttribute);
        var offsetToCheck = originalOffset - stickyMenuHeight;
        var currentScroll = $(window).scrollTop();
        var currentSticky = current.hasClass('sticky');

        if($(window).innerWidth() < desktopMinimum) {
          offsetToCheck = originalOffset - mobileMenuHeight;
        }

        if(currentScroll >= offsetToCheck && !currentSticky) {
          dummy.addClass('open');
          current.addClass('sticky');
        }
        else if(currentScroll < offsetToCheck && currentSticky) {
          current.removeClass('sticky');
          dummy.removeClass('open');
        }
      });
    }
  };


})(jQuery, Drupal);
