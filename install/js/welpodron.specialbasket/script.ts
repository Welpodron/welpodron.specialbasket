((window: any) => {
  if (window.welpodron && window.welpodron.templater) {
    if (window.welpodron.specialbasket) {
      return;
    }

    const MODULE_BASE = 'specialbasket';

    const EVENT_ADD_BEFORE = `welpodron.${MODULE_BASE}:add:before`;
    const EVENT_ADD_AFTER = `welpodron.${MODULE_BASE}:add:after`;

    const ATTRIBUTE_BASE = `data-w-${MODULE_BASE}`;
    const ATTRIBUTE_RESPONSE = `${ATTRIBUTE_BASE}-response`;
    const ATTRIBUTE_CONTROL = `${ATTRIBUTE_BASE}-control`;
    const ATTRIBUTE_INPUT = `${ATTRIBUTE_BASE}-input`;
    const ATTRIBUTE_LINK = `${ATTRIBUTE_BASE}-link`;
    const ATTRIBUTE_LINK_COUNTER = `${ATTRIBUTE_LINK}-counter`;
    const ATTRIBUTE_TOTAL_PRICE = `${ATTRIBUTE_BASE}-total-price`;
    const ATTRIBUTE_LINK_ACTIVE = `${ATTRIBUTE_LINK}-active`;
    const ATTRIBUTE_ACTION = `${ATTRIBUTE_BASE}-action`;
    const ATTRIBUTE_ACTION_ARGS = `${ATTRIBUTE_ACTION}-args`;
    const ATTRIBUTE_ACTION_FLUSH = `${ATTRIBUTE_ACTION}-flush`;

    type _BitrixResponse = {
      data: any;
      status: 'success' | 'error';
      errors: {
        code: string;
        message: string;
        customData: string;
      }[];
    };

    type SpecialBasketConfigType = {
      forceSessid?: boolean;
      forceItems?: boolean;
    };

    type SpecialBasketPropsType = {
      sessid: string;
      items: string[];
      config?: SpecialBasketConfigType;
    };

    class SpecialBasket {
      sessid = '';

      items = new Set<string>();

      supportedActions = ['add', 'set'];

      constructor({ sessid, items, config = {} }: SpecialBasketPropsType) {
        if ((SpecialBasket as any).instance) {
          if (config.forceSessid) {
            if (sessid) {
              (SpecialBasket as any).instance.sessid = sessid;
            }
          } else {
            // if (sessid) {
            //   (SpecialBasket as any).instance.sessid = sessid;
            // }
          }

          if (config.forceItems) {
            if (Array.isArray(items) && items.length) {
              (SpecialBasket as any).instance.items = new Set(
                items.map((item: string) => item.toString())
              );
            }
          } else {
            // if (Array.isArray(items) && items.length) {
            //   (SpecialBasket as any).instance.items = new Set(
            //     items.map((item: string) => item.toString())
            //   );
            // }
          }

          return (SpecialBasket as any).instance;
        }

        this.setSessid(sessid);
        this.setItems(items);

        if (document.readyState === 'loading') {
          document.addEventListener(
            'DOMContentLoaded',
            this.handleDocumentLoaded,
            {
              once: true,
            }
          );
        } else {
          this.handleDocumentLoaded();
        }

        if (window.JCCatalogItem) {
          window.JCCatalogItem.prototype.changeInfo = this.handleOfferChange(
            window.JCCatalogItem.prototype.changeInfo
          );
        }

        if (window.JCCatalogElement) {
          window.JCCatalogElement.prototype.changeInfo = this.handleOfferChange(
            window.JCCatalogElement.prototype.changeInfo
          );
        }

        (SpecialBasket as any).instance = this;
      }

      handleDocumentLoaded = () => {
        document.querySelectorAll(`[${ATTRIBUTE_LINK}]`).forEach((link) => {
          if (this.items.size) {
            link.setAttribute(ATTRIBUTE_LINK_ACTIVE, '');
          } else {
            link.removeAttribute(ATTRIBUTE_LINK_ACTIVE);
          }

          const counters = link.querySelectorAll(`[${ATTRIBUTE_LINK_COUNTER}]`);

          counters.forEach((counter) => {
            counter.textContent = this.items.size.toString();
          });
        });
      };

      handleOfferChange = (func: Function) => {
        const self = this;

        return function () {
          let beforeId =
            this.productType === 3
              ? this.offerNum > -1
                ? this.offers[this.offerNum].ID
                : 0
              : this.product.id;

          let afterId = -1;

          let index = -1;
          let boolOneSearch = true;

          for (let i = 0; i < this.offers.length; i++) {
            boolOneSearch = true;
            for (let j in this.selectedValues) {
              if (this.selectedValues[j] !== this.offers[i].TREE[j]) {
                boolOneSearch = false;
                break;
              }
            }
            if (boolOneSearch) {
              index = i;
              break;
            }
          }

          let canBuy = true;

          if (index > -1) {
            afterId = this.offers[index].ID;
            canBuy = this.offers[index].CAN_BUY;
          } else {
            afterId = this.product.id;
            canBuy = this.product.canBuy;
          }

          if (beforeId && afterId && beforeId !== afterId) {
            document
              .querySelectorAll(
                `input[name="products[${beforeId}]"][${ATTRIBUTE_INPUT}]`
              )
              .forEach((element) => {
                const actionArgs = (element as HTMLInputElement).getAttribute(
                  'name'
                );

                if (!actionArgs) {
                  return;
                }

                if (!canBuy) {
                  element.setAttribute('disabled', '');
                } else {
                  element.removeAttribute('disabled');
                }

                element.setAttribute('name', `products[${afterId.toString()}]`);
              });
          }

          func.call(this);
        };
      };

      setSessid = (sessid: string) => {
        this.sessid = sessid;
      };

      setItems = (items: string[]) => {
        this.items = new Set(items.map((item) => item.toString()));

        document.querySelectorAll(`[${ATTRIBUTE_LINK}]`).forEach((el) => {
          if (this.items.size) {
            el.setAttribute(ATTRIBUTE_LINK_ACTIVE, '');
          } else {
            el.removeAttribute(ATTRIBUTE_LINK_ACTIVE);
          }
          el.querySelectorAll(`[${ATTRIBUTE_LINK_COUNTER}]`).forEach((el) => {
            el.textContent = this.items.size.toString();
          });
        });
      };

      add = async ({
        args,
        event,
      }: {
        args?: string | null | FormData;
        event?: Event;
      }) => {
        if (!args) {
          return;
        }

        const controls = document.querySelectorAll(
          `[${ATTRIBUTE_CONTROL}][${ATTRIBUTE_ACTION}][${ATTRIBUTE_ACTION_ARGS}="${args}"]`
        );

        controls.forEach((control) => {
          control.setAttribute('disabled', '');
        });

        let data = args instanceof FormData ? args : new FormData();

        // composite and deep cache fix
        if (window.BX && window.BX.bitrix_sessid) {
          this.setSessid(window.BX.bitrix_sessid());
        }

        data.set('sessid', this.sessid);

        if (!(args instanceof FormData)) {
          let json = '';

          try {
            JSON.parse(args);
            json = args;
          } catch (_) {
            json = JSON.stringify(args);
          }

          data.set('args', json);
        }

        let dispatchedEvent = new CustomEvent(EVENT_ADD_BEFORE, {
          bubbles: true,
          cancelable: false,
        });

        document.dispatchEvent(dispatchedEvent);

        let responseData: any = {};
        let bitrixResponse: _BitrixResponse | null = null;

        try {
          const response = await fetch(
            '/bitrix/services/main/ajax.php?action=welpodron%3Aspecialbasket.Receiver.add',
            {
              method: 'POST',
              body: data,
            }
          );

          if (!response.ok) {
            throw new Error(response.statusText);
          }

          if (response.redirected) {
            window.location.href = response.url;
            return;
          }

          bitrixResponse = await response.json();

          if (!bitrixResponse) {
            throw new Error('Ожидался другой формат ответа от сервера');
          }

          if (bitrixResponse.status === 'error') {
            console.error(bitrixResponse);

            const error = bitrixResponse.errors[0];

            if (!event || !event?.target) {
              return bitrixResponse;
            }

            const target = (event.target as Element).closest(
              `[${ATTRIBUTE_CONTROL}][${ATTRIBUTE_ACTION}]`
            );

            if (!target || !target.parentElement) {
              return bitrixResponse;
            }

            let div = target.parentElement.querySelector(
              `[${ATTRIBUTE_RESPONSE}]`
            );

            if (!div) {
              div = document.createElement('div');
              div.setAttribute(ATTRIBUTE_RESPONSE, '');
              target.parentElement.appendChild(div);
            }

            window.welpodron.templater.renderHTML({
              string: error.message,
              container: div as HTMLElement,
              config: {
                replace: true,
              },
            });
          } else {
            responseData = bitrixResponse.data;

            if (responseData.HTML != null) {
              if (event && event?.target) {
                const target = (event.target as Element).closest(
                  `[${ATTRIBUTE_CONTROL}][${ATTRIBUTE_ACTION}]`
                );

                if (target && target.parentElement) {
                  let div = target.parentElement.querySelector(
                    `[${ATTRIBUTE_RESPONSE}]`
                  );

                  if (!div) {
                    div = document.createElement('div');
                    div.setAttribute(ATTRIBUTE_RESPONSE, '');
                    target.parentElement.appendChild(div);
                  }

                  (window as any).welpodron.templater.renderHTML({
                    string: responseData.HTML,
                    container: div as HTMLElement,
                    config: {
                      replace: true,
                    },
                  });
                }
              }
            }

            if (responseData.CURRENT_PRODUCTS) {
              const currentProductsIds = Object.keys(
                responseData.CURRENT_PRODUCTS
              );

              this.setItems(currentProductsIds);
            }
          }
        } catch (error) {
          console.error(error);
        } finally {
          dispatchedEvent = new CustomEvent(EVENT_ADD_AFTER, {
            bubbles: true,
            cancelable: false,
            detail: responseData,
          });

          document.dispatchEvent(dispatchedEvent);

          controls.forEach((control) => {
            control.removeAttribute('disabled');
          });
        }

        return bitrixResponse;
      };

      set = async ({
        args,
        event,
      }: {
        args?: string | null | FormData;
        event?: Event;
      }) => {
        if (!args) {
          return;
        }

        const controls = document.querySelectorAll(
          `[${ATTRIBUTE_CONTROL}][${ATTRIBUTE_ACTION}][${ATTRIBUTE_ACTION_ARGS}="${args}"]`
        );

        controls.forEach((control) => {
          control.setAttribute('disabled', '');
        });

        let data = args instanceof FormData ? args : new FormData();

        // composite and deep cache fix
        if (window.BX && window.BX.bitrix_sessid) {
          this.setSessid(window.BX.bitrix_sessid());
        }

        data.set('sessid', this.sessid);

        if (!(args instanceof FormData)) {
          let json = '';

          try {
            JSON.parse(args);
            json = args;
          } catch (_) {
            json = JSON.stringify(args);
          }

          data.set('args', json);
        }

        let dispatchedEvent = new CustomEvent(EVENT_ADD_BEFORE, {
          bubbles: true,
          cancelable: false,
        });

        document.dispatchEvent(dispatchedEvent);

        let responseData: any = {};
        let bitrixResponse: _BitrixResponse | null = null;

        try {
          const response = await fetch(
            '/bitrix/services/main/ajax.php?action=welpodron%3Aspecialbasket.Receiver.set',
            {
              method: 'POST',
              body: data,
            }
          );

          if (!response.ok) {
            throw new Error(response.statusText);
          }

          bitrixResponse = await response.json();

          if (!bitrixResponse) {
            throw new Error('Ожидался другой формат ответа от сервера');
          }

          if (bitrixResponse.status === 'error') {
            console.error(bitrixResponse);

            const error = bitrixResponse.errors[0];

            if (!event || !event?.target) {
              return;
            }

            const target = (event.target as Element).closest(
              `[${ATTRIBUTE_CONTROL}][${ATTRIBUTE_ACTION}]`
            );

            if (!target || !target.parentElement) {
              return;
            }

            let div = target.parentElement.querySelector(
              `[${ATTRIBUTE_RESPONSE}]`
            );

            if (!div) {
              div = document.createElement('div');
              div.setAttribute(ATTRIBUTE_RESPONSE, '');
              target.parentElement.appendChild(div);
            }

            window.welpodron.templater.renderHTML({
              string: error.message,
              container: div as HTMLElement,
              config: {
                replace: true,
              },
            });
          } else {
            responseData = bitrixResponse.data;

            if (responseData.HTML != null) {
              if (event && event?.target) {
                const target = (event.target as Element).closest(
                  `[${ATTRIBUTE_CONTROL}][${ATTRIBUTE_ACTION}]`
                );

                if (target && target.parentElement) {
                  let div = target.parentElement.querySelector(
                    `[${ATTRIBUTE_RESPONSE}]`
                  );

                  if (!div) {
                    div = document.createElement('div');
                    div.setAttribute(ATTRIBUTE_RESPONSE, '');
                    target.parentElement.appendChild(div);
                  }

                  (window as any).welpodron.templater.renderHTML({
                    string: responseData.HTML,
                    container: div as HTMLElement,
                    config: {
                      replace: true,
                    },
                  });
                }
              }
            }

            if (responseData.TOTAL_PRICE) {
              document
                .querySelectorAll(`[${ATTRIBUTE_TOTAL_PRICE}]`)
                .forEach((el) => {
                  el.textContent = responseData.TOTAL_PRICE;
                });
            } else {
              document
                .querySelectorAll(`[${ATTRIBUTE_TOTAL_PRICE}]`)
                .forEach((el) => {
                  el.textContent = '-';
                });
            }

            if (responseData.CURRENT_PRODUCTS) {
              const currentProductsIds = Object.keys(
                responseData.CURRENT_PRODUCTS
              );

              this.setItems(currentProductsIds);
            }
          }
        } catch (error) {
          console.error(error);
        } finally {
          dispatchedEvent = new CustomEvent(EVENT_ADD_AFTER, {
            bubbles: true,
            cancelable: false,
            detail: responseData,
          });

          document.dispatchEvent(dispatchedEvent);

          controls.forEach((control) => {
            control.removeAttribute('disabled');
          });
        }

        return bitrixResponse;
      };
    }

    window.welpodron.specialbasket = SpecialBasket;
  }
})(window);
