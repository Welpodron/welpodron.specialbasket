"use strict";
((window) => {
    if (window.welpodron && window.welpodron.templater) {
        if (!window.welpodron.specialbasket) {
            return;
        }
        if (!window.welpodron.forms) {
            window.welpodron.forms = {};
        }
        if (!window.welpodron.forms.specialbasket) {
            window.welpodron.forms.specialbasket = {};
        }
        if (window.welpodron.forms.specialbasket.calculator) {
            return;
        }
        //! Огромное спасибо epoberezkin fast-deep-equal: https://github.com/epoberezkin/fast-deep-equal/
        function equal(a, b) {
            if (a === b)
                return true;
            if (a && b && typeof a == "object" && typeof b == "object") {
                if (a.constructor !== b.constructor)
                    return false;
                var length, i, keys;
                if (Array.isArray(a)) {
                    length = a.length;
                    if (length != b.length)
                        return false;
                    for (i = length; i-- !== 0;)
                        if (!equal(a[i], b[i]))
                            return false;
                    return true;
                }
                if (a.constructor === RegExp)
                    return a.source === b.source && a.flags === b.flags;
                if (a.valueOf !== Object.prototype.valueOf)
                    return a.valueOf() === b.valueOf();
                if (a.toString !== Object.prototype.toString)
                    return a.toString() === b.toString();
                keys = Object.keys(a);
                length = keys.length;
                if (length !== Object.keys(b).length)
                    return false;
                for (i = length; i-- !== 0;)
                    if (!Object.prototype.hasOwnProperty.call(b, keys[i]))
                        return false;
                for (i = length; i-- !== 0;) {
                    var key = keys[i];
                    if (!equal(a[key], b[key]))
                        return false;
                }
                return true;
            }
            // true if both NaN, false otherwise
            return a !== a && b !== b;
        }
        const GENERAL_ERROR_CODE = "FORM_GENERAL_ERROR";
        const FIELD_VALIDATION_ERROR_CODE = "FIELD_VALIDATION_ERROR";
        class CalculatorForm {
            element;
            timeout = 0;
            isDisabled = false;
            _temp = {};
            mutationObserver;
            constructor({ element, config = {} }) {
                this.element = element;
                this.element
                    .querySelectorAll("input, textarea, select")
                    .forEach((element) => {
                    const field = element;
                    if (field.name && !field.disabled) {
                        if (this._temp[field.name]) {
                            if (Array.isArray(this._temp[field.name])) {
                                this._temp[field.name].push(field.value);
                            }
                            else {
                                this._temp[field.name] = [
                                    this._temp[field.name],
                                    field.value,
                                ];
                            }
                        }
                        else {
                            this._temp[field.name] = field.value;
                        }
                    }
                });
                this.mutationObserver = new MutationObserver((mutations) => {
                    const event = new Event("input");
                    this.element.dispatchEvent(event);
                });
                this.mutationObserver.observe(this.element, {
                    subtree: true,
                    childList: true,
                });
                this.element.removeEventListener("input", this.handleFormInput);
                this.element.addEventListener("input", this.handleFormInput);
            }
            handleFormInput = async (event) => {
                if (this.timeout) {
                    clearTimeout(this.timeout);
                }
                if (this.isDisabled) {
                    return;
                }
                this.timeout = setTimeout(async () => {
                    this.isDisabled = true;
                    const data = new FormData();
                    const _temp = {};
                    //!TODO: Сделать нормальный билдер FormData из элементов формы (из-за чекбоксов,радио-кнопок,select multiple)
                    this.element
                        .querySelectorAll("input, textarea, select")
                        .forEach((element) => {
                        const field = element;
                        if (field.name && !field.disabled) {
                            data.append(field.name, field.value);
                            if (_temp[field.name]) {
                                if (Array.isArray(_temp[field.name])) {
                                    _temp[field.name].push(field.value);
                                }
                                else {
                                    _temp[field.name] = [_temp[field.name], field.value];
                                }
                            }
                            else {
                                _temp[field.name] = field.value;
                            }
                        }
                    });
                    debugger;
                    if (equal(this._temp, _temp)) {
                        this.isDisabled = false;
                        return;
                    }
                    else {
                        //! Так как в целом не подразумевается сложных структур в форме (только простые поля), то можно оставить так
                        this._temp = JSON.parse(JSON.stringify(_temp));
                    }
                    // composite and deep cache fix
                    if (window.BX && window.BX.bitrix_sessid) {
                        data.set("sessid", window.BX.bitrix_sessid());
                    }
                    const basket = new window.welpodron.specialbasket({
                        sessid: window.BX && window.BX.bitrix_sessid
                            ? window.BX.bitrix_sessid()
                            : null,
                        items: [],
                    });
                    try {
                        debugger;
                        const result = await basket.set({
                            args: data,
                            event,
                        });
                        if (!result) {
                            this.isDisabled = false;
                            return;
                        }
                        if (result.status === "error") {
                            const error = result.errors[0];
                            if (error.code === FIELD_VALIDATION_ERROR_CODE) {
                                const field = this.element.querySelectorAll(`[name="${error.customData}"]`)[0];
                                if (field) {
                                    field.setCustomValidity(error.message);
                                    field.reportValidity();
                                    field.addEventListener("input", () => {
                                        field.setCustomValidity("");
                                        field.reportValidity();
                                        field.checkValidity();
                                    }, {
                                        once: true,
                                    });
                                }
                            }
                            this.isDisabled = false;
                            return;
                        }
                        if (result.status === "success") {
                            this.isDisabled = false;
                        }
                    }
                    catch (error) {
                        console.error(error);
                    }
                    finally {
                        this.isDisabled = false;
                    }
                }, 650);
            };
        }
        window.welpodron.forms.specialbasket.calculator = CalculatorForm;
    }
})(window);
//# sourceMappingURL=script.js.map