## Thay đổi gì

<!-- Một hai câu. Nếu sửa lỗi, mô tả lỗi trước rồi mới tới cách sửa. -->

## Vì sao

<!-- Phần quan trọng hơn. Code nói được "làm gì", không nói được "vì sao". -->

## Đã kiểm

- [ ] `composer test` xanh
- [ ] `composer analyse` xanh
- [ ] `composer format` đã chạy
- [ ] Có test mới cho hành vi mới, hoặc test khoá lại lỗi vừa sửa

## Nếu đụng tới payload gửi đi Zalo

- [ ] Đã gọi **thật** với OA/Bot thật, không chỉ chạy test với fake
- [ ] Đã dán request/response thật vào PR (xoá token, secret, dữ liệu cá nhân)
- [ ] Đã cập nhật mục "Những gì chưa được xác minh" trong README nếu liên quan

> Fake chỉ chứng minh code khớp với **giả định của chúng ta** về Zalo API.
> Đã có ba lỗi lọt qua trọn bộ test vì fake trả về hình dạng body không có
> thật. Payload mới thì phải chạy thật.

## Phá vỡ tương thích?

<!-- Có thì mô tả người dùng phải sửa gì. Package đang ở 0.x nên chấp nhận
     được, nhưng vẫn phải ghi vào CHANGELOG. -->
