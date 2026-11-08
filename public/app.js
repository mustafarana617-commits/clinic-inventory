var allButtons = document.querySelectorAll('button');

allButtons.forEach(function (currentButton) {

    var buttonText = currentButton.textContent.trim();

    if (buttonText === 'Deactivate' || buttonText === 'Cancel') {

        currentButton.addEventListener('click', function (event) {

            var userConfirmation = confirm('Are you sure?');

            if (userConfirmation === false) {
                event.preventDefault();
            }

        });
    }

});
