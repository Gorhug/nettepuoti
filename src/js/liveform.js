import { LiveForm } from 'live-form-validation';

LiveForm.setOptions( {
    controlErrorClass: 'input-invalid',
    controlValidClass: 'input-valid',
    messageErrorClass: 'text-error',
    showMessageClassOnParent: false,
    messageErrorPrefix: '⚠️&nbsp;',
 
});
