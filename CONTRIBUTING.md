# Đóng góp

Cảm ơn bạn đã quan tâm tới `field-vn/zalo`.

## Quy trình

1. Fork và tạo branch từ `main`
2. Viết test cho thay đổi của bạn
3. Đảm bảo `composer test`, `composer analyse`, `composer format` đều sạch
4. Mở PR, mô tả rõ **vấn đề** đang giải quyết, không chỉ mô tả thay đổi

## Commit

Theo [Conventional Commits](https://www.conventionalcommits.org/):

```
feat(scope): thêm tính năng X
fix(scope): sửa lỗi Y
docs: cập nhật hướng dẫn Z
```

## Breaking change

Thay đổi phá vỡ tương thích cần được thảo luận trong issue **trước khi** viết code. Thêm method vào interface công khai cũng là breaking change.

## Setup

```bash
git clone https://github.com/field-vn/zalo.git
cd zalo
composer install
composer test
```

Muốn thử trong một app Laravel thật, dùng path repository:

```json
{
    "repositories": [
        { "type": "path", "url": "../zalo", "options": { "symlink": true } }
    ]
}
```
