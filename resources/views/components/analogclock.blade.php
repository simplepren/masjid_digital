<div class="analog-clock-wrapper">
    <style>
        .clock {
            background: radial-gradient(var(--color-teal-300), var(--color-teal-700),var(--color-teal-900));
            width: 200px;
            height: 200px;
            margin: 20px auto;
            border-radius: 50%;
            border: 10px solid var(--color-teal-600);
            position: relative;
            box-shadow: 0 10px 20px -5px rgba(0,0,0,0.5);
        }

        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #ccc;
            top: 0; left: 0; right: 0; bottom: 0;
            margin: auto;
            position: absolute;
            z-index: 10;
            box-shadow: 0 2px 4px -1px black;
        }

        .hour-hand {
            position: absolute;
            z-index: 5;
            width: 3px;
            height: 45px;
            background: var(--color-gray-200);
            top: 41px;
            left: 50%;
            margin-left: -1.5px;
            border-top-left-radius: 50%;
            border-top-right-radius: 50%;
            transform-origin: 50% 50px;
        }

        .minute-hand {
            position: absolute;
            z-index: 6;
            width: 3px;
            height: 70px;
            background: var(--color-gray-100);
            top: 20px;
            left: 50%;
            margin-left: -1.5px;
            border-top-left-radius: 50%;
            border-top-right-radius: 50%;
            transform-origin: 50% 73px;
        }

        .second-hand {
            position: absolute;
            z-index: 7;
            width: 1.5px;
            height: 84px;
            background: gold;
            top: 5px;
            left: 50%;
            margin-left: -0.75px;
            border-top-left-radius: 50%;
            border-top-right-radius: 50%;
            transform-origin: 50% 87px;
        }

        .clock span {
            display: inline-block;
            position: absolute;
            color: var(--color-gray-200);
            font-size: 16px;
            font-family: sans-serif;
            font-weight: 700;
            z-index: 4;
        }

        .h12 { top: 21px; left: 50%; margin-left: -7px; }
        .h3  { top: 78px; right: 21px; }
        .h6  { bottom: 21px; left: 50%; margin-left: -4px; }
        .h9  { left: 22px; top: 78px; }

        .diallines {
            position: absolute;
            z-index: 2;
            width: 1.5px;
            height: 10px;
            background: var(--color-gray-100);
            left: 50%;
            margin-left: -0.75px;
            transform-origin: 50% 90px;
        }

        .diallines:nth-of-type(5n) {
            width: 3px;
            height: 17px;
            transform-origin: 50% 90px;
        }

        .info {
            position: absolute;
            width: 84px;
            height: 14px;
            border-radius: 5px;
            background: #ccc;
            text-align: center;
            line-height: 14px;
            color: #000;
            font-size: 8px;
            left: 50%;
            margin-left: -42px;
            font-family: sans-serif;
            font-weight: 700;
            z-index: 3;
            letter-spacing: 2px;
        }
        .date { top: 56px; }
        .day { top: 140px; }
       
    </style>

    <div class="clock">
        <div class="dot"></div>
        <div class="hour-hand"></div>
        <div class="minute-hand"></div>
        <div class="second-hand"></div>
        <span class="h3">3</span>
        <span class="h6">6</span>
        <span class="h9">9</span>
        <span class="h12">12</span>
        <div class="diallines"></div>
    </div>

    <script>
        (function() {
            var clockEl = document.querySelector('.clock');
            // Cek jika elemen ada untuk menghindari error JS
            if (!clockEl) return;

            // Generate dial lines hanya jika belum ada banyak
            if (clockEl.querySelectorAll('.diallines').length < 10) {
                for (var i = 1; i < 60; i++) {
                    var newLine = document.createElement('div');
                    newLine.className = 'diallines';
                    newLine.style.transform = "rotate(" + 6 * i + "deg)";
                    clockEl.appendChild(newLine);
                }
            }

            function updateClock() {
                var weekday = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"],
                    d = new Date(),
                    h = d.getHours(),
                    m = d.getMinutes(),
                    s = d.getSeconds(),
                    date = d.getDate(),
                    month = d.getMonth() + 1,
                    year = d.getFullYear(),
                    hDeg = h * 30 + m * 0.5,
                    mDeg = m * 6 + s * 0.1,
                    sDeg = s * 6;
                
                var hEl = clockEl.querySelector('.hour-hand'),
                    mEl = clockEl.querySelector('.minute-hand'),
                    sEl = clockEl.querySelector('.second-hand'),
                    dateEl = clockEl.querySelector('.date'),
                    dayEl = clockEl.querySelector('.day');
                
                if (hEl) hEl.style.transform = "rotate("+hDeg+"deg)";
                if (mEl) mEl.style.transform = "rotate("+mDeg+"deg)";
                if (sEl) sEl.style.transform = "rotate("+sDeg+"deg)";
                if (dateEl) dateEl.innerHTML = date + "/" + (month < 10 ? "0" + month : month) + "/" + year;
                if (dayEl) dayEl.innerHTML = weekday[d.getDay()];
            }

            setInterval(updateClock, 1000);
            updateClock();
        })();
    </script>
</div>