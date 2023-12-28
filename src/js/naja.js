import naja from 'naja';
import netteForms from 'nette-forms';

netteForms.initOnLoad();
naja.formsHandler.netteForms = netteForms;
naja.initialize();