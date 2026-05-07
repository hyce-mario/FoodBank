{{--
    Bot defense fields — pair this with the BotDefense middleware on the
    POST route. Renders two hidden inputs:
      1. website_url honeypot — visually hidden, ARIA-hidden, tab-skipped,
         autocomplete-disabled. Real users never touch it; bots autofill it.
      2. _form_ts — HMAC-signed render timestamp. The middleware rejects
         submissions that arrive faster than MIN_FILL_SECONDS (currently 3s).

    Drop in any public form *inside* the <form> element:
        <x-bot-defense />
--}}
<div aria-hidden="true"
     style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">
    {{-- Plausible-looking field name so bots fill it; obscure enough that
         password managers / browser autofill won't touch it. --}}
    <label for="bd_website_url">Website (leave this field blank)</label>
    <input type="text"
           name="{{ \App\Http\Middleware\BotDefense::HONEYPOT_FIELD }}"
           id="bd_website_url"
           value=""
           tabindex="-1"
           autocomplete="off">
</div>

<input type="hidden"
       name="{{ \App\Http\Middleware\BotDefense::TIMESTAMP_FIELD }}"
       value="{{ \App\Http\Middleware\BotDefense::signedTimestamp() }}">
