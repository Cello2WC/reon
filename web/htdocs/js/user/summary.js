"use strict";

window.addEventListener("DOMContentLoaded", event => {
	initRevealPasswordButton();
	initAdapterModelImage();
});

function initRevealPasswordButton() {
	document.getElementById("dionPasswordRevealButton").addEventListener("click", event => {
		const passwordInput = document.getElementById("dionPassword");
		event.target.remove();
		passwordInput.value = passwordInput.dataset["password"];
	});
}

function initAdapterModelImage() {
	document.getElementById('adapterModelSelect').addEventListener('change', event => {
		document.getElementById('adapterModelImage').src = `/png/adapter/${document.getElementById('adapterModelSelect').value}.png`
	});
}