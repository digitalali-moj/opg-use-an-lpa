/* istanbul ignore file */
import './scss.js';
import { initAll, Accordion } from 'govuk-frontend';
import jsEnabled from './javascript/jsEnabled';
import disableButtonOnClick from './javascript/disableButtonOnClick';
import copyAccessCode from './javascript/copyAccessCode';
import cookieConsent from './javascript/cookieConsent';
import sessionDialog from './javascript/sessionDialog';
import showHidePassword from  './javascript/showHidePassword';

Accordion.prototype.updateOpenAllButton = function (expanded) {
    var newButtonText = expanded ? this.$module.dataset.closetext : this.$module.dataset.opentext;
    newButtonText += `<span class="govuk-visually-hidden"> ${this.$module.dataset.sectiontext}</span>`;
    this.$openAllButton.setAttribute('aria-expanded', expanded);
    this.$openAllButton.innerHTML = newButtonText;
};
initAll();
jsEnabled(document.body);
disableButtonOnClick(document.getElementsByTagName('form'));
new cookieConsent(document.getElementsByClassName('cookie-banner')[0], window.location.pathname === '/cookies');
copyAccessCode();
showHidePassword();
if (document.getElementsByClassName('js-signed-in').length > 0) {
    new sessionDialog(document.getElementById("dialog"));
}
