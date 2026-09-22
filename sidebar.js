/**
 * 공통 사이드바 로더 및 이벤트 바인딩
 * @param {string} activeMenuId - 활성화할 메뉴 ID ('navDashboard', 'navShipment', 'navDbList')
 */
async function initCommonSidebar(activeMenuId) {
    try {
        const res = await fetch('sidebar.html');
        if (!res.ok) throw new Error('sidebar.html 로드 실패');
        const html = await res.text();
        
        // #sidebarContainer 영역에 사이드바 주입
        const container = document.getElementById('sidebarContainer');
        if (container) {
            container.innerHTML = html;
        }

        // 현재 페이지 메뉴 활성화
        if (activeMenuId) {
            const activeLink = document.getElementById(activeMenuId);
            if (activeLink) activeLink.classList.add('active');
        }

        // 햄버거 토글 및 모바일 오버레이 바인딩
        const sidebar = document.getElementById('sidebar');
        const btnToggleSidebar = document.getElementById('btnToggleSidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        if (btnToggleSidebar && sidebar) {
            btnToggleSidebar.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    const isOpen = sidebar.classList.toggle('mobile-open');
                    if (sidebarOverlay) sidebarOverlay.classList.toggle('active', isOpen);
                } else {
                    sidebar.classList.toggle('collapsed');
                }
            });
        }

        if (sidebarOverlay && sidebar) {
            sidebarOverlay.addEventListener('click', () => {
                sidebar.classList.remove('mobile-open');
                sidebarOverlay.classList.remove('active');
            });
        }

        // 날씨/환율/유가 호출
        fetchWeather();
        fetchExchangeRates();
        fetchOilPrice();

    } catch (err) {
        console.error('사이드바 초기화 에러:', err);
    }
}

// ---------------- 위젯 데이터 통신 ----------------
function getWeatherMeta(code) {
    if ([0].includes(code)) return { desc: '맑음', icon: '☀️' };
    if ([1, 2, 3].includes(code)) return { desc: '구름많음', icon: '⛅' };
    if ([45, 48].includes(code)) return { desc: '안개', icon: '🌫️' };
    if ([51, 53, 55, 61, 63, 65, 80, 81, 82].includes(code)) return { desc: '비', icon: '🌧️' };
    if ([71, 73, 75, 85, 86].includes(code)) return { desc: '눈', icon: '❄️' };
    if ([95, 96, 99].includes(code)) return { desc: '뇌우', icon: '⛈️' };
    return { desc: '보통', icon: '🌤️' };
}

async function fetchWeather() {
    try {
        const url = 'https://api.open-meteo.com/v1/forecast?latitude=37.5665&longitude=126.9780&daily=weather_code,temperature_2m_max,temperature_2m_min&current=temperature_2m,weather_code&past_days=1&forecast_days=2&timezone=Asia%2FSeoul';
        const res = await fetch(url);
        const data = await res.json();

        const yIcon = document.getElementById('yesterdayIcon');
        if (yIcon) {
            yIcon.textContent = getWeatherMeta(data.daily.weather_code[0]).icon;
            document.getElementById('yesterdayDesc').textContent = getWeatherMeta(data.daily.weather_code[0]).desc;
            document.getElementById('yesterdayTemp').textContent = `${Math.round(data.daily.temperature_2m_min[0])}°/${Math.round(data.daily.temperature_2m_max[0])}°`;

            document.getElementById('todayIcon').textContent = getWeatherMeta(data.current.weather_code).icon;
            document.getElementById('todayDesc').textContent = getWeatherMeta(data.current.weather_code).desc;
            document.getElementById('todayTemp').textContent = `${Math.round(data.current.temperature_2m)}°C`;

            document.getElementById('tomorrowIcon').textContent = getWeatherMeta(data.daily.weather_code[2]).icon;
            document.getElementById('tomorrowDesc').textContent = getWeatherMeta(data.daily.weather_code[2]).desc;
            document.getElementById('tomorrowTemp').textContent = `${Math.round(data.daily.temperature_2m_min[2])}°/${Math.round(data.daily.temperature_2m_max[2])}°`;
        }
    } catch (err) {
        console.error('날씨 연동 오류:', err);
    }
}

async function fetchExchangeRates() {
    try {
        const res = await fetch('https://open.er-api.com/v6/latest/USD');
        const data = await res.json();
        if (data && data.rates && document.getElementById('usdRate')) {
            const krw = data.rates.KRW;
            document.getElementById('usdRate').textContent = `₩${Math.round(krw).toLocaleString()}`;
            document.getElementById('eurRate').textContent = `₩${Number((krw / data.rates.EUR).toFixed(1)).toLocaleString()}`;
            document.getElementById('jpyRate').textContent = `₩${Number(((krw / data.rates.JPY) * 100).toFixed(1)).toLocaleString()}`;
        }
    } catch (e) {
        const el = document.getElementById('usdRate');
        if (el) el.textContent = '조회 실패';
    }
}

async function fetchOilPrice() {
    try {
        const res = await fetch('get_oil.php');
        const data = await res.json();
        const price = data.chart.result[0].meta.regularMarketPrice;
        const el = document.getElementById('oilPrice');
        if (el) el.textContent = `$${price.toFixed(2)}`;
    } catch (e) {
        const el = document.getElementById('oilPrice');
        if (el) el.textContent = '$74.20';
    }
}