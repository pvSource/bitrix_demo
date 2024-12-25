<?php if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die(); ?>

<section class="ip-detail">
    <div class="ip-detail__container">
        <h3 class="ip-detail__title">Поиск геолокации по IP-адресу</h3>

        <div class="ip-detail__form-box">
            <label class="ip-detail__input-box" for="geo-ip">
                <input type="text" id="geo-ip" placeholder="IPv4">
            </label>

            <button type="submit" id="geo-submit">Проверить</button>
        </div>

        <div class="ip-detail__info"></div>
    </div>
</section>
