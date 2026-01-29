<div class="flip-card">
    <div class="flip-inner" :class="{ 'flip': digit.flip }">
        <div class="flip-front" x-text="digit.prev"></div>
        <div class="flip-back" x-text="digit.current"></div>
    </div>
</div>