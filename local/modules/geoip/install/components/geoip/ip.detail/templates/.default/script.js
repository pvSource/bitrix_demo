document.addEventListener('DOMContentLoaded', () => {

    const initComponent = (componentElem) => {
        let inputElem = componentElem.querySelector('#geo-ip');
        let submitButton = componentElem.querySelector('#geo-submit');
        let ipDetailInfoElem = componentElem.querySelector('.ip-detail__info');

        if (!inputElem || !submitButton || !ipDetailInfoElem) {
            console.warn('Компонент не инициализирован, ошибка верстки');
            return;
        }

        inputElem.addEventListener('input', (e) => {
            ipDetailInfoElem.classList.remove('ip-detail__info_incorrect');
            ipDetailInfoElem.classList.remove('ip-detail__info_correct');
            ipDetailInfoElem.innerHTML = '';
        });

        submitButton.addEventListener('click', (e) => {
            const currentIp = inputElem.value;

            BX.ajax.runComponentAction('geoip:ip.detail', 'getIpInfo', {
                mode: 'class',
                data: {
                    ip: currentIp
                }
            }).then(
                (response) => {
                    ipDetailInfoElem.classList.add('ip-detail__info_correct');

                    ipDetailInfoElem.innerHTML = `
                            <p>IP: <span class="ip-detail__value">${response.data.ip}</span></p>
                            <p>Страна: <span class="ip-detail__value">${response.data.country}</span></p>
                            <p>Регион: <span class="ip-detail__value">${response.data.region}</span></p>
                            <p>Город: <span class="ip-detail__value">${response.data.city}</span></p>
                            <p>Источник информации: <span class="ip-detail__value">${response.data.source}</span></p>
                        `;

                },
                (response) => {
                    ipDetailInfoElem.classList.add('ip-detail__info_incorrect');

                    ipDetailInfoElem.innerHTML = `
                            <p>IP: <span class="ip-detail__value">${currentIp}</span></p>
                            <p><span class="ip-detail__error-message">${response.errors[0].message}</span></p>
                        `;
                }
            );
        });

        componentElem.dataset.isInit = 'y';
    }

    let componentElem = document.querySelector('.ip-detail:not([data-is-init])');
    initComponent(componentElem);
});