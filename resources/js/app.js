/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
import { clock, css, flipClock, theme, elapsedTime, counter } from 'flipclock';

window.FlipClock = {
    clock,
    css,
    flipClock,
    theme,
    elapsedTime,
    counter
};