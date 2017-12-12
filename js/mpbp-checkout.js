/**
 * Copyright © 2016 Vaimo Finland Oy, Suomen Maksuturva Oy
 * See LICENSE.txt for license details.
 */

/**
 * Modified version of opcheckout.js that uses only payment_method and review steps
 */

var Checkout = Class.create();
Checkout.prototype = {
    initialize: function(accordion, urls){
        this.accordion = accordion;
        this.progressUrl = urls.progress;
        this.reviewUrl = urls.review;
        this.failureUrl = urls.failure;
        this.loadWaiting = false;
        this.steps = ['shipping_method', 'review'];
        //We use billing as beginning step since progress bar tracks from billing
        this.currentStep = 'shipping_method';

        this.accordion.sections.each(function(section) {
            Event.observe($(section).down('.step-title'), 'click', this._onSectionClick.bindAsEventListener(this));
        }.bind(this));

        this.accordion.disallowAccessToNextSections = true;

        this.gotoSection('shipping_method', true);
    },

    /**
     * Section header click handler
     *
     * @param event
     */
    _onSectionClick: function(event) {
        var section = $(Event.element(event).up('.section'));
        if (section.hasClassName('allow')) {
            Event.stop(event);
            this.gotoSection(section.readAttribute('id').replace('opc-', ''), false);
            return false;
        }
    },

    ajaxFailure: function(){
        location.href = encodeURI(this.failureUrl);
    },

    reloadProgressBlock: function(toStep) {
        this.reloadStep(toStep);
    },

    reloadStep: function(prevStep) {
        var updater = new Ajax.Updater(prevStep + '-progress-opcheckout', this.progressUrl, {
            method:'get',
            onFailure:this.ajaxFailure.bind(this),
            onComplete: function(){
                this.checkout.resetPreviousSteps();
            },
            parameters:prevStep ? { prevStep:prevStep } : null
        });
    },

    reloadReviewBlock: function(){
        new Ajax.Updater('checkout-review-load', this.reviewUrl, {method: 'get', onFailure: this.ajaxFailure.bind(this)});
    },

    _disableEnableAll: function(element, isDisabled) {
        var descendants = element.descendants();
        for (var k in descendants) {
            descendants[k].disabled = isDisabled;
        }
        element.disabled = isDisabled;
    },

    setLoadWaiting: function(step, keepDisabled) {
        var container;
        if (step) {
            if (this.loadWaiting) {
                this.setLoadWaiting(false);
            }
            container = $(step+'-buttons-container');
            container.addClassName('disabled');
            container.setStyle({opacity:.5});
            this._disableEnableAll(container, true);
            Element.show(step+'-please-wait');
        } else {
            if (this.loadWaiting) {
                container = $(this.loadWaiting+'-buttons-container');
                var isDisabled = (keepDisabled ? true : false);
                if (!isDisabled) {
                    container.removeClassName('disabled');
                    container.setStyle({opacity:1});
                }
                this._disableEnableAll(container, isDisabled);
                Element.hide(this.loadWaiting+'-please-wait');
            }
        }
        this.loadWaiting = step;
    },

    gotoSection: function (section, reloadProgressBlock) {

        if (reloadProgressBlock) {
            this.reloadProgressBlock(this.currentStep);
        }
        this.currentStep = section;
        var sectionElement = $('opc-' + section);
        sectionElement.addClassName('allow');
        this.accordion.openSection('opc-' + section);
        if(!reloadProgressBlock) {
            this.resetPreviousSteps();
        }
    },

    resetPreviousSteps: function () {
        var stepIndex = this.steps.indexOf(this.currentStep);

        //Clear other steps if already populated through javascript
        for (var i = stepIndex; i < this.steps.length; i++) {
            var nextStep = this.steps[i];
            var progressDiv = nextStep + '-progress-opcheckout';
            if ($(progressDiv)) {
                //Remove the link
                $(progressDiv).select('.changelink').invoke('remove');
                $(progressDiv).select('dt').invoke('removeClassName','complete');
                //Remove the content
                $(progressDiv).select('dd.complete').invoke('remove');
            }
        }
    },

    changeSection: function (section) {
        var changeStep = section.replace('opc-', '');
        this.gotoSection(changeStep, false);
    },

    setShippingMethod: function() {
        //this.nextStep();
        this.gotoSection('review', true);
        //this.accordion.openNextSection(true);
    },

    setReview: function() {
        this.reloadProgressBlock();
        //this.nextStep();
        //this.accordion.openNextSection(true);
    },

    back: function(){
        if (this.loadWaiting) return;
        //Navigate back to the previous available step
        var stepIndex = this.steps.indexOf(this.currentStep);
        var section = this.steps[--stepIndex];
        var sectionElement = $('opc-' + section);

        //Traverse back to find the available section. Ex Virtual product does not have shipping section
        while (sectionElement === null && stepIndex > 0) {
            --stepIndex;
            section = this.steps[stepIndex];
            sectionElement = $('opc-' + section);
        }
        this.changeSection('opc-' + section);
    },

    setStepResponse: function(response){
        if (response.update_section) {
            $('checkout-'+response.update_section.name+'-load').update(response.update_section.html);
        }
        if (response.allow_sections) {
            response.allow_sections.each(function(e){
                $('opc-'+e).addClassName('allow');
            });
        }

        if (response.goto_section) {
            this.gotoSection(response.goto_section, true);
            return true;
        }
        if (response.redirect) {
            location.href = encodeURI(response.redirect);
            return true;
        }
        return false;
    }
};

// shipping method
var ShippingMethod = Class.create();
ShippingMethod.prototype = {
    initialize: function(form, saveUrl){
        this.form = form;
        if ($(this.form)) {
            $(this.form).observe('submit', function(event){this.save();Event.stop(event);}.bind(this));
        }
        this.saveUrl = saveUrl;
        this.validator = new Validation(this.form);
        this.onSave = this.nextStep.bindAsEventListener(this);
        this.onComplete = this.resetLoadWaiting.bindAsEventListener(this);
    },

    validate: function() {
        var methods = document.getElementsByName('shipping_method');
        if (methods.length==0) {
            alert(Translator.translate('Your order cannot be completed at this time as there is no shipping methods available for it. Please make necessary changes in your shipping address.').stripTags());
            return false;
        }

        if(!this.validator.validate()) {
            return false;
        }

        for (var i=0; i<methods.length; i++) {
            if (methods[i].checked) {
                return true;
            }
        }
        alert(Translator.translate('Please specify shipping method.').stripTags());
        return false;
    },

    save: function(){

        if (checkout.loadWaiting!=false) return;
        if (this.validate()) {
            checkout.setLoadWaiting('shipping-method');
            new Ajax.Request(
                this.saveUrl,
                {
                    method:'post',
                    onComplete: this.onComplete,
                    onSuccess: this.onSave,
                    onFailure: checkout.ajaxFailure.bind(checkout),
                    parameters: Form.serialize(this.form)
                }
            );
        }
    },

    resetLoadWaiting: function(transport){
        checkout.setLoadWaiting(false);
    },

    nextStep: function(transport){
        var response = transport.responseJSON || transport.responseText.evalJSON(true) || {};

        if (response.error) {
            alert(response.message.stripTags().toString());
            return false;
        }

        if (response.update_section) {
            $('checkout-'+response.update_section.name+'-load').update(response.update_section.html);
        }

        if (response.goto_section) {
            checkout.gotoSection(response.goto_section, true);
            checkout.reloadProgressBlock();
            return;
        }

        checkout.setShippingMethod();
    }
};

var Review = Class.create();
Review.prototype = {
    initialize: function(saveUrl, successUrl, agreementsForm){
        this.saveUrl = saveUrl;
        this.successUrl = successUrl;
        this.agreementsForm = agreementsForm;
        this.onSave = this.nextStep.bindAsEventListener(this);
        this.onComplete = this.resetLoadWaiting.bindAsEventListener(this);
    },

    save: function(){
        if (checkout.loadWaiting!=false) return;
        checkout.setLoadWaiting('review');

        params = [];
        params.save = true;

        new Ajax.Request(
            this.saveUrl,
            {
                method:'post',
                parameters:params,
                onComplete: this.onComplete,
                onSuccess: this.onSave,
                onFailure: checkout.ajaxFailure.bind(checkout)
            }
        );
    },

    resetLoadWaiting: function(transport){
        checkout.setLoadWaiting(false, this.isSuccess);
    },

    nextStep: function(transport){
        if (transport) {
            var response = transport.responseJSON || transport.responseText.evalJSON(true) || {};

            if (response.redirect) {
                this.isSuccess = true;
                location.href = encodeURI(response.redirect);
                return;
            }
            if (response.success) {
                this.isSuccess = true;
                location.href = encodeURI(this.successUrl);
            }
            else{
                var msg = response.error_messages;
                if (Object.isArray(msg)) {
                    msg = msg.join("\n").stripTags().toString();
                }
                if (msg) {
                    alert(msg);
                }
            }

            if (response.update_section) {
                $('checkout-'+response.update_section.name+'-load').update(response.update_section.html);
            }

            if (response.goto_section) {
                checkout.gotoSection(response.goto_section, true);
            }
        }
    },

    isSuccess: false
};
