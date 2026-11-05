/**
 * jQuery Form
 *
 * Interface for dynamic manipulation of HTML form.
 *
 * @class Form
 *
 * @param {jQuery}        $form                            Form element
 * @param {object}        [options]                        Default config override
 * @param {int|false}     [options.messageScrollOffsetTop] Scroll top offset in px
 * @param {int}           [options.messageScrollSpeed]     Scroll duration in ms
 * @param {string}        [options.inputClassInvalid]      Invalid input class name
 * @param {string}        [options.inputClassValue]        Not-empty input class name
 * @param {function|null} [options.readyFunction]          Called on initialization
 * @param {function|null} [options.successFunction]        Called on success
 * @param {function|null} [options.failFunction]           Called on validation error
 * @param {function|null} [options.refreshFunction]        Returns promise to refresh session after 401
 * @param {string}        [options.eventBusyName]          Name of event on busy change
 * @param {bool}          [options.withCredentials]        Cross-site requests with credentials
 *
 * @requires jQuery
 *
 * @fires formBusy[options.eventBusyName] (busy, self, $form) on state change
 * @listens submit of the form
 * @listens change/paste/keyup of the form's inputs
 *
 * @version 1.2.1
 */
function Form($form, options)
{
  /**
   * @property {object} config Default configuration
   */
  var config = {
    messageScrollOffsetTop : 140,
    messageScrollSpeed     : 500,
    inputClassInvalid      : 'form__input--is-invalid',
    inputClassValue        : 'form__input--has-value',
    readyFunction          : null,
    successFunction        : null,
    failFunction           : null,
    refreshFunction        : null,
    eventBusyName          : 'formBusy',
    withCredentials        : false
  };

  /** @property {Form} self Self-reference */
  var self = this;

  /** @property {bool} busy State of form */
  var busy = false;

  /** @property {string} messages Validation messages selector */
  var messages = '[data-form-invalid]';

  /** @property {string} message Validation message selector */
  var message = '[data-form-invalid="{name}"]';

  /** @property {string} messageFirst First validation message selector */
  var messageFirst = messages + ':visible:first';

  /** @property {string} inputsAll All inputs selector */
  var inputsAll = ':input[name]';

  /** @property {string} inputs Inputs selector */
  var inputs = inputsAll + ':not(:disabled)';

  /** @property {string} input Input selector */
  var input = ':input[{attribute}="{value}"]';

  /** @property {string} inputChecked Checked input selector */
  var inputChecked = input + ':checked';

  /**
   * Fill variables into template
   *
   * @param {string} template  Template
   * @param {object} variables Variables
   *
   * @return {string} Filled template
   */
  this.fillString = function(template, variables)
  {
    $.each(variables, function(key, variable) {
      var pattern = new RegExp('{' + key + '}', 'g');

      template = template.replace(pattern, variable);
    });

    return template;
  }

  /**
   * Return type of element
   *
   * @param {jQuery} $element Element
   *
   * @return {string} Tag name or type attribute
   */
  this.typeOfElement = function($element)
  {
    var tag = $element
          .prop('tagName')
          .toLowerCase(),
        isInput = tag == 'input',
        type    = isInput
          ? $element
              .attr('type')
              .toLowerCase()
          : null;

    return isInput
      ? type
      : tag;
  }

  /**
   * Slide to first validation message
   *
   * @return {Form} Self-reference
   */
  this.slideToMessage = function()
  {
    var $element = $form.find(messageFirst);

    if ($element.length && config.messageScrollOffsetTop !== false) {
      $('html, body').animate({
        scrollTop: $element.offset().top - config.messageScrollOffsetTop
      }, config.messageScrollSpeed);
    }

    return self;
  }

  /**
   * Set button action state
   *
   * @param {bool} state State
   *
   * @return {Form} Self-reference
   */
  this.action = function(state)
  {
    self
      .input('submit')
      .attr('data-form-action', state
        ? ''
        : null)
      .attr('disabled', state
        ? 'disabled'
        : false);

    return self;
  }

  /**
   * Set form busy state
   *
   * @param {bool} state State
   *
   * @return {Form} Self-reference
   */
  this.busy = function(state)
  {
    busy = state;

    $(document).trigger(config.eventBusyName, [state, self, $form]);

    return self.action(state);
  }

  /**
   * Return input element
   *
   * If name "submit" is given, submit button is returned instead
   *
   * @param {string} name Input name
   *
   * @return {jQuery} Input
   */
  this.input = function(name)
  {
    var isSubmit = name == 'submit',
        selector = this.fillString(input, {
          'attribute' : isSubmit
            ? 'type'
            : 'name',
          'value' : isSubmit
            ? 'submit'
            : name});

    return $form.find(selector);
  }

  /**
   * Return input elements
   *
   * @param {string} name Input name
   *
   * @return {array} Inputs
   */
  this.inputs = function(names)
  {
    return $form.find(
      $.map(names, function(name) {
        return self.fillString(input, {
          'attribute' : 'name',
          'value'     : name
        });
      }).join(',')
    );
  }

  /**
   * Set input as invalid
   *
   * @param {string} name  Input name
   * @param {bool}   state State
   *
   * @return {Form} Self-reference
   */
  this.invalid = function(name, state)
  {
    var $element = self.input(name);

    if (state) {
      $element.addClass(config.inputClassInvalid);
    } else {
      $element.removeClass(config.inputClassInvalid);
    }

    return self;
  }

  /**
   * Set form as invalid
   *
   * @param {bool} state State
   *
   * @return {Form} Self-reference
   */
  this.invalidForm = function(state)
  {
    var $elements = $form.find(inputs);

    $.each($elements, function() {
      self.invalid(
        $(this).attr('name'),
        state
      );
    });

    return self;
  }

  /**
   * Focus input
   *
   * @param {string} name  Input name
   * @param {bool}   state State
   *
   * @return {Form} Self-reference
   */
  this.focus = function(name, state)
  {
    var $element = self.input(name);

    if (state) {
      $element.focus();
    } else {
      $element.blur();
    }

    return self;
  }

  /**
   * Reset input
   *
   * @param {string} name Input name
   *
   * @return {Form} Self-reference
   */
  this.reset = function(name)
  {
    self
      .input(name)
      .val('')
      .change();

    return self;
  }

  /**
   * Reset form
   *
   * @return {Form} Self-reference
   */
  this.resetForm = function()
  {
    // $form.reset();

    $form
      .find(inputsAll)
      .not(':radio, :checkbox')
      .val('');

    $form
      .find(inputsAll)
      .filter(':radio, :checkbox')
      .prop('checked', false)
      .prop('selected', false)
      .change();

    return self;
  }

  /**
   * Disable input
   *
   * @param {string} name  Input name
   * @param {bool}   state State
   *
   * @return {Form} Self-reference
   */
  this.disable = function(name, state)
  {
    $.each(self.input(name), function() {
      var $element = $(this);

      $element
        .attr('data-form-disabled', state
          ? $element.is(':disabled')
          : $element.attr('data-form-disabled'))
        .prop('disabled', state
          ? 'disabled'
          : $element.attr('data-form-disabled') === 'true');
    });

    return self;
  }

  /**
   * Disable form
   *
   * @param {bool} state State
   *
   * @return {Form} Self-reference
   */
  this.disableForm = function(state)
  {
    var $elements = $form.find(inputsAll);

    $.each($elements, function() {
      self.disable(
        $(this).attr('name'),
        state
      );
    });

    return self;
  }

  /**
   * Check input value
   *
   * @param {string} name Input name
   *
   * @return {Form} Self-reference
   */
  this.checkValue = function(name)
  {
    var $element = self.input(name),
        value    = self.getValue(name);

    if (value) {
      $element.addClass(config.inputClassValue);

      self
        .invalid(name, false)
        .hideMessage(name);
    } else {
      $element.removeClass(config.inputClassValue);
    }

    return self;
  }

  /**
   * Check all inputs value
   *
   * @return {Form} Self-reference
   */
  this.checkValues = function()
  {
    var $elements = $form.find(inputsAll);

    $.each($elements, function() {
      self.checkValue(
        $(this).attr('name')
      );
    });

    return self;
  }

  /**
   * Hide message
   *
   * @param {string} name Input name
   *
   * @return {Form} Self-reference
   */
  this.hideMessage = function(name)
  {
    var selector = this.fillString(message, {
      'name' : name
    });

    $form
      .find(selector)
      .fadeOut();

    return self;
  }

  /**
   * Hide all messages
   *
   * @return {Form} Self-reference
   */
  this.hideMessages = function()
  {
    $form
      .find(messages)
      .hide();

    return this;
  }

  /**
   * Show message
   *
   * @param {string} name Input name
   * @param {string} text Message text
   *
   * @return {Form} Self-reference
   */
  this.showMessage = function(name, text)
  {
    var selector = this.fillString(message, {
      'name' : name
    });

    $form
      .find(selector)
      .text(text)
      .fadeIn();

    return self;
  }

  /**
   * Show messages
   *
   * @param {object} data Form validation result
   *
   * @return {Form} Self-reference
   */
  this.showMessages = function(data)
  {
    $.each(data, function(name, state) {
      if (state !== true) {
        self
          .invalid(name, true)
          .showMessage(name, state);
      }
    });

    return self.slideToMessage();
  }

  /**
   * Get input value
   *
   * @param {string} name Input name
   *
   * @return {string} Value
   */
  this.getValue = function(name)
  {
    var $element = self.input(name),
        value    = null;

    switch (self.typeOfElement($element)) {
      case 'checkbox':
        var $checked = $element.filter(':checked');
        
        value = $element.length > 1
          ? ($.map($checked, function(current) {
              return $(current).is(':checked')
                ? ($(current).val() || true)
                : false;
            }).join(',') || '')
          : $element.is(':checked');
        break;

      case 'number':
        value = +$element.val();
        break;

      case 'date':
        value = $element.val();
        
        if (value === '') {
          value = null;
        }
        break;

      default:
        if (name.substr(name.length - 2, 2) === '[]') {
          value = $.map($element, function(current) {
            return $(current).val();
          });
        } else {
          value = $element.val();
        }
        break;
    }

    switch ($element.attr('data-form-cast')) {
      case 'int':
        value = +value;
        break;

      case 'float':
        value = parseFloat(
          value.replace(',', '.')
        );
        break
    }

    return value !== 'null'
      ? value
      : null;
  }

  /**
   * Get form values
   *
   * @return {object} Input names and values
   */
  this.getValues = function()
  {
    var result    = {},
        $elements = $form.find(inputs);

    $.each($elements, function() {
      var name = $(this).attr('name');
      var key  = name;

      if (name.substr(name.length - 2, 2) === '[]') {
         key = name.substr(0, name.length - 2);
      }

      result[key] = self.getValue(name);
    });

    // console.log(result); // DEBUG

    return result;
  }

  /**
   * Set input value
   *
   * @param {string} name  Input name
   * @param {string} value Value
   *
   * @return {Form} Self-reference
   */
  this.setValue = function(name, value)
  {
    var $element = self.input(name);

    // console.log(value);

    if (value && value.toString().substr(value.length - 8) == '00:00:00') {
      value = value.substr(0, 10);
    }

    switch (self.typeOfElement($element)) {
      case 'checkbox':
        if ($element.length > 1) {
          $element
            .filter(function() {
              return $.inArray(
                $(this).attr('value'),
                value.toString().split(',')
              ) !== -1;
            })
            .prop('checked', 'checked');
        } else {
          $element.prop('checked', value
            ? 'checked'
            : false
          );
        }
        break;

      case 'radio':
        var selector = self.fillString(input, {
              'attribute' : 'value',
              'value'     : value
            }),
            $checked = $element
              .filter(selector)
              .prop('checked', 'checked');
        break;

      default:
        $element.val(value);
        break;
    }

    $element.trigger('change');

    return self;
  }

  /**
   * Set form values
   *
   * @param {object} data Form values
   *
   * @return {Form} Self-reference
   */
  this.setValues = function(data)
  {
    $.each(data, function(name, value) {
      self.setValue(name, value);
    });

    return self;
  }

  /**
   * Validate form
   *
   * @async
   */
  this.validateForm = function(target)
  {
    var values = self.getValues();

    self
      .disableForm(true)
      .focus('submit', true)
      .busy(true);

    var type = ($form.data('form-method') || 'POST').toUpperCase();

    function getCookie(name)
    {
      var prefix = name + '=';
      var cookie = document.cookie
        .split('; ')
        .find(function(row) {
          return row.startsWith(prefix);
        });

      return cookie ? cookie.slice(prefix.length) : null;
    }

    function getAjaxOptions()
    {
      var csrf = getCookie('__Host-csrf_token');
      var needsCsrf = ['POST', 'DELETE', 'PUT', 'PATCH'].includes(type);

      return {
        'url'         : target,
        'data'        : JSON.stringify(values),
        'xhrFields'   : {
          'withCredentials' : config.withCredentials
        },
        'contentType' : 'application/json',
        'headers'     : needsCsrf && csrf
          ? {'X-CSRF-Token': csrf}
          : {},
        'processData' : false,
        'type'        : type
      };
    }

    function reject()
    {
      var deferred = $.Deferred();

      deferred.reject.apply(deferred, arguments);

      return deferred.promise();
    }

    function handleSuccess(data, status, xhr)
    {
      if (config.successFunction) {
        config.successFunction(self, $form, data, xhr);
      }

      return data;
    }

    function handleFailure(xhr, status, error)
    {
      if (xhr.responseJSON && xhr.responseJSON.invalid_parameters) {
        self.showMessages(xhr.responseJSON.invalid_parameters);
      }

      if (config.failFunction) {
        config.failFunction(self, $form, xhr);
      }

      return reject(xhr, status, error);
    }

    function refresh(xhr)
    {
      var result;

      if (!config.refreshFunction) {
        return reject(xhr);
      }

      try {
        result = config.refreshFunction(self, $form, xhr);
      } catch (error) {
        return reject(error);
      }

      if (
        !result
        || (
          typeof result.then !== 'function'
          && typeof result.promise !== 'function'
        )
      ) {
        return reject(xhr);
      }

      return $.when(result);
    }

    function request(canRefresh)
    {
      return $.ajax(getAjaxOptions()).then(
        handleSuccess,
        function(xhr, status, error) {
          if (canRefresh && xhr.status === 401 && config.refreshFunction) {
            return refresh(xhr).then(
              function() {
                return request(false);
              },
              function() {
                return handleFailure(xhr, status, error);
              }
            );
          }

          return handleFailure(xhr, status, error);
        }
      );
    }

    request(true).always(function() {
      self
        .busy(false)
        .disableForm(false);
    });
  }

  /**
   * Submit form to custom action
   */
  this.submitTo = function(action)
  {
    if (!busy) {
      self
        .hideMessages()
        .invalidForm(false)
        .validateForm(action);
    }
  }

  /**
   * Initialization
   */
  this.init = function()
  {
    $.extend(config, options || {});

    self
      .hideMessages()
      .checkValues()
      .busy(false);

    if (config.readyFunction) {
      config.readyFunction(self, $form);
    }
  }

  /**
   * Form submit event
   */
  $form
    .off('submit')
    .on('submit', function(event)
  {
    event.preventDefault();

    if (!busy) {
      self
        .hideMessages()
        .invalidForm(false)
        .validateForm(
          $form.data('form-action')
        );
    }
  });

  /**
   * Input change event
   */
  $form.on('change paste keyup', inputs, function(event) {
    if ((event.keyCode || event.which) != 13) {
      self.checkValue(
        $(this).attr('name')
      );
    }
  });

  /**
   * Class constructor
   */
  self.init();
}

(function($) {
  $.fn.form = function(options) {
    return new Form(
      $(this),
      options
    );
  };
}(jQuery));
