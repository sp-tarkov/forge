@props(['href' => '#', 'title' => ''])

<style>
    .sf-icon {
        position: relative;
        display: inline-block;
        width: 24px;
        height: 24px;
        background-color: white;
        border-radius: 50%;
        text-align: center;
        line-height: 24px;
        font-size: 12px;
        font-weight: bold;
        color: black;
        text-decoration: none;
        transition: background-color 0.2s ease;
        overflow: hidden;
    }

    .sf-icon:hover {
        background-color: black;
        color: white;
        animation: chaosGlitch 0.1s infinite, randomRotate 0.3s infinite, fontFlicker 0.2s infinite, italicFlicker 0.25s infinite;
    }

    @keyframes chaosGlitch {
        0% {
            transform: translateX(0) translateY(0) rotate(0deg);
        }

        10% {
            transform: translateX(-1px) translateY(1px) rotate(-2deg);
        }

        20% {
            transform: translateX(1px) translateY(-1px) rotate(1deg);
        }

        30% {
            transform: translateX(-2px) translateY(0px) rotate(-3deg);
        }

        40% {
            transform: translateX(2px) translateY(1px) rotate(2deg);
        }

        50% {
            transform: translateX(0px) translateY(-2px) rotate(-1deg);
        }

        60% {
            transform: translateX(-1px) translateY(2px) rotate(3deg);
        }

        70% {
            transform: translateX(1px) translateY(-1px) rotate(-2deg);
        }

        80% {
            transform: translateX(-2px) translateY(-1px) rotate(1deg);
        }

        90% {
            transform: translateX(2px) translateY(2px) rotate(-3deg);
        }

        100% {
            transform: translateX(0) translateY(0) rotate(0deg);
        }
    }

    @keyframes randomRotate {
        0% {
            transform: rotate(0deg);
        }

        25% {
            transform: rotate(-15deg);
        }

        50% {
            transform: rotate(20deg);
        }

        75% {
            transform: rotate(-10deg);
        }

        100% {
            transform: rotate(0deg);
        }
    }

    @keyframes fontFlicker {
        0% {
            font-family: inherit;
        }

        20% {
            font-family: 'Creepster', cursive, serif;
        }

        40% {
            font-family: inherit;
        }

        60% {
            font-family: 'Creepster', cursive, serif;
        }

        80% {
            font-family: inherit;
        }

        100% {
            font-family: 'Creepster', cursive, serif;
        }
    }

    @keyframes italicFlicker {
        0% {
            font-style: normal;
        }

        30% {
            font-style: italic;
        }

        60% {
            font-style: normal;
        }

        90% {
            font-style: italic;
        }

        100% {
            font-style: normal;
        }
    }

    .sf-icon::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.1) 50%, transparent 70%);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .sf-icon:hover::before {
        opacity: 1;
        animation: scan 0.4s infinite;
    }

    @keyframes scan {
        0% {
            transform: translateX(-100%) rotate(-45deg);
        }

        100% {
            transform: translateX(100%) rotate(-45deg);
        }
    }
</style>
<link
    href="https://fonts.googleapis.com/css2?family=Creepster&display=swap"
    rel="preload"
    as="style"
    onload="this.onload=null;this.rel='stylesheet'"
>
<a
    href="{{ $href }}"
    title="{{ $title }}"
    class="sf-icon"
>SF</a>
