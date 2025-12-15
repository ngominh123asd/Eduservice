function xacNhanDangXuat() {
    if (confirm('Bạn có chắc chắn muốn đăng xuất?')) {
        sessionStorage.clear();
        localStorage.removeItem('userToken');
        window.location.href = '/dangnhap/dangnhap.html';
    }
}