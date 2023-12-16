import '../css/app.css';
// import 'htmx.org';
import naja from 'naja';
import netteForms from 'nette-forms';
netteForms.initOnLoad();



// window.htmx = require('htmx.org');
console.log('Hello World from app.js');
naja.formsHandler.netteForms = netteForms;
naja.initialize();