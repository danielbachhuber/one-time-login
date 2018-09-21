document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.one-time-login-form')
    .forEach((form) => form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!window.oneTimeLogin) {
            return;
        }
        
        if (event.target.elements.email && event.target.elements.email.value) {
            fetch(
                window.oneTimeLogin.ajax_url,
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: new Headers({'Content-Type': 'application/x-www-form-urlencoded'}),
                    body: `action=send_email&security=${window.oneTimeLogin.security}&email=${event.target.elements.email.value}`,
                }
            )
            .then((resp) => resp.json())
            .then((data) => {
                if (data.success) {
                    const response = form.querySelector('.one-time-login-response');
                    const label = form.querySelector('label');
                    const inputs = form.querySelectorAll('input');
                    if (response && label && inputs) {
                        response.style.display = 'block';
                        label.style.display = 'none';
                        inputs.forEach(input => input.style.display = 'none');
                    }
                }
            })
            .catch(function(error) {
              console.log(JSON.stringify(error));
            });
        }
    }));
});
