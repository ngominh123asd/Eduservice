<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../dangnhap/xulydangnhap.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EDUSERVICE</title>
    <!-- Load all CSS first -->
    <link href="https://fonts.googleapis.com/css2?family=Lexend&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/components/header.css">
    <link rel="stylesheet" href="/components/footer.css">
    <link rel="stylesheet" href="css/hdsaudn.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
</head>
<body>
    <div id="header-placeholder"></div>
    <div class="container">
        <h1>Chương trình đang diễn ra </h1>
        <p>Dưới đây là những dự án nổi bật đang được triển khai bởi cộng đồng tình nguyện.</p>
<div class="swiper mySwiper">
  <div class="swiper-wrapper">
    <div class="swiper-slide">
  <img src="images/chunhatxanh.jpg" alt="Chủ nhật xanh">
  <div class="event-info">
    <h2>Chủ nhật xanh</h2>
    <p>🏡 Đây là hoạt động thiết thực thể hiện tinh thần xung kích, tình nguyện của tuổi trẻ trong việc chung tay bảo vệ môi trường. Đồng thời phát huy tinh thần tự giác, trách nhiệm của mỗi sinh viên trong việc giữ gìn vệ sinh chung, góp phần xây dựng môi trường học tập “ xanh - sạch - đẹp”.</p>
    <a href="https://www.facebook.com/CLB.SVTN.UEd/posts/pfbid02uctXeCfN4d7KXvnUMuyEHvinLkTVnTMncZZYwHbnysKiDrQyHqGsk6tYFP5NFLa2l" class="btn-info" target="_blank">Tìm hiểu thêm</a>
    <a href="https://docs.google.com/forms/d/1zgZi424oT5zjf8cenS55D3eYSokrsA6hh8PF2kDdNXE/edit?hl=vi" class="btn-register" target="_blank">Đăng ký tham gia</a>
  </div>
</div>
    <div class="swiper-slide">
  <img src="images/hatmam.jpg" alt="Hạt mầm ước mơ">
  <div class="event-info">
    <h2>Hạt mầm ước mơ</h2>
    <p>Giáo dục là nền tảng của tương lai, giúp các em nhỏ xây dựng nền tảng vững chắc để phát triển và có cuộc sống tốt đẹp hơn. Thông qua việc giáo dục, chương trình góp phần tạo ra sự thay đổi tích cực và bền vững trong cộng đồng. Mỗi bài học, mỗi kiến thức truyền đạt chính là những hạt giống được gieo trồng, sẽ nảy mầm và phát triển, mang lại những kết quả tốt đẹp cho xã hội trong tương lai.</p>
    <a href="https://www.facebook.com/ProjectClub.UEd/posts/pfbid02fB2emLLRcbe6mtcWjLyPcmjXxaMDiL7A6C6TRFGsfVWwb1YfHhsovnjaSw7Z4ELEl" class="btn-info" target="_blank">Tìm hiểu thêm</a>
    <a href="https://docs.google.com/forms/d/1zgZi424oT5zjf8cenS55D3eYSokrsA6hh8PF2kDdNXE/edit?hl=vi" class="btn-register" target="_blank">Đăng ký tham gia</a>
  </div>
</div>
 <div class="swiper-slide">
      <img src="images/giothong.jpg" alt="Giọt hồng ước mơ">
      <div class="event-info">
        <h2>Giọt hồng ước mơ</h2>
        <p> 👉 Hiến máu tình nguyện là một nghĩa cử cao đẹp, đó không chỉ thể hiện trách nhiệm của cá nhân đối với cộng đồng, mà còn là lòng thương yêu “Một người vì mọi người”. Mỗi giọt máu được cho đi là mỗi lần hy vọng lại được gieo mầm trong tâm hồn của người bệnh, là sức mạnh tiếp thêm động lực, niềm tin cho họ vượt qua bệnh tật.</p>
        <a href="https://www.facebook.com/CLB.SVTN.UEd/posts/1101144185154109:538413952659620" class="btn-info" target="_blank">Tìm hiểu thêm</a>
        <a href="https://docs.google.com/forms/d/1zgZi424oT5zjf8cenS55D3eYSokrsA6hh8PF2kDdNXE/edit?hl=vi" class="btn-register" target="_blank">Đăng ký tham gia</a>
      </div>
    </div>
    <div class="swiper-slide">
  <img src="images/gopxanh.jpg" alt="Góp xanh - Evergreen">
  <div class="event-info">
    <h2>“EVERGREEN” – Góp xanh</h2>
     <p>🌱 Trong chương trình, những bộ giáo trình cũ được sinh viên quyên góp sẽ được tái sử dụng hoặc trao tặng cho các khóa sau, giảm lãng phí giấy. Đổi lại, mỗi người sẽ nhận được một mầm cây xanh. Những cây này sau đó được tập thể CLB cùng học sinh chung tay trồng trong khuôn viên trường, góp phần tạo không gian học tập trong lành, mát mẻ hơn.</p>
     <a href="https://www.facebook.com/ProjectClub.UEd/posts/pfbid0qtSEPjgDU1xtxpX8Hrd2Je98MBMZbPUSkBZLB2NXU5iaDEb8mHAQeAnQiF5jEV3ml" class="btn-info" target="_blank">Tìm hiểu thêm</a>
     <a href="https://docs.google.com/forms/d/1zgZi424oT5zjf8cenS55D3eYSokrsA6hh8PF2kDdNXE/edit?hl=vi" class="btn-register" target="_blank">Đăng ký tham gia</a>
  </div>
</div>
    <div class="swiper-slide">
      <img src="images/muahexanh.jpg" alt="Mùa hè xanh">
      <div class="event-info">
        <h2>Mùa hè xanh</h2>
         <p>Là một trong những chương trình tình nguyện trọng điểm hàng năm, với mục tiêu mang lại sự giúp đỡ thiết thực cho cộng đồng, đặc biệt là ở những vùng sâu, vùng xa.</p>
        <a href="https://www.facebook.com/SuctreUEd/posts/pfbid02QPqbhMnH15VAAMbKpVtWY9mQeB19ryEUBHGJcuv8v3NoRdnXhqS8xRqHNkSf2LZel" class="btn-info" target="_blank">Tìm hiểu thêm</a>
        <a href="https://docs.google.com/forms/d/1zgZi424oT5zjf8cenS55D3eYSokrsA6hh8PF2kDdNXE/edit?hl=vi" class="btn-register" target="_blank">Đăng ký tham gia</a>
      </div>
    </div>
    <div class="swiper-slide">
      <img src="images/muadongam.jpg" alt="Mùa đông ấm">
      <div class="event-info">
        <h2>Mùa đông ấm</h2>
         <p>Là chương trình tình nguyện thường niên của Đoàn Thanh niên Trường Đại học Giáo dục, nhằm mục đích hỗ trợ những hoàn cảnh khó khăn trong mùa đông lạnh giá, giúp đỡ họ về vật chất và tinh thần.</p>
        <a href="https://www.facebook.com/SuctreUEd/posts/pfbid02rYsofrSipeggPES6dQWDNoBsA4Lo95LePkbV2G9yYoNN3vFnjPjJHeUhnNi7c4Rtl" class="btn-info" target="_blank">Tìm hiểu thêm</a>
        <a href="https://docs.google.com/forms/d/1zgZi424oT5zjf8cenS55D3eYSokrsA6hh8PF2kDdNXE/edit?hl=vi" class="btn-register" target="_blank">Đăng ký tham gia</a>
      </div>
    </div>
  </div>
 
  <div class="swiper-button-next"></div>
  <div class="swiper-button-prev"></div>
  <div class="swiper-pagination"></div>
</div>
    </div>
    <div id="footer-placeholder"></div>

    <!-- Load scripts in correct order -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/components/darkmode.js"></script>

    <script>
    // Load header first and initialize Swiper after header is loaded
    fetch('/components/header.php')
        .then(response => response.text())
        .then(data => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(data, 'text/html');
            document.getElementById('header-placeholder').innerHTML = doc.body.innerHTML;
            
            // Initialize Swiper only after header is loaded
            initSwiper();
        });

    // Load footer independently
    fetch('/components/footer.html')
        .then(response => response.text())
        .then(data => {
            document.getElementById('footer-placeholder').innerHTML = data;
        });

    // Separate Swiper initialization function
    function initSwiper() {
        const swiper = new Swiper(".mySwiper", {
            slidesPerView: 1,
            spaceBetween: 30,
            loop: true,
            speed: 800,
            grabCursor: true,
            navigation: {
                nextEl: ".swiper-button-next",
                prevEl: ".swiper-button-prev",
                enabled: true
            },
            pagination: {
                el: ".swiper-pagination",
                clickable: true
            },
            autoplay: {
                delay: 5000,
                disableOnInteraction: false
            }
        });
    }

    // Keep the existing logout confirmation function
    function xacNhanDangXuat() {
        Swal.fire({
        title: 'Bạn có chắc chắn muốn đăng xuất?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Đăng xuất',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#2E7D32',
        cancelButtonColor: '#3085d6'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "../dangnhap/dangxuat.php";
        }
    });
    }
    </script>
</body>
</html>