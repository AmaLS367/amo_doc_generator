test('rowData parses row correctly', () => {
  document.body.innerHTML = `
    <tr>
      <td></td>
      <td><input class="n" value="Test Service"></td>
      <td><input class="q" value="2"></td>
      <td><input class="u" value="1000"></td>
      <td><input class="d" value="10"></td>
    </tr>
  `;
  const tr = document.querySelector('tr');
  const result = rowData(tr);
  expect(result.name).toBe("Test Service");
  expect(result.qty).toBe(2);
  expect(result.unit).toBe(1000);
  expect(result.dp).toBe(10);
  expect(result.after).toBe(1800);
});

// JS тест запускается через Jest или любой headless runner — по желанию, если будешь тестить JS.
// JS test runs through Jest or any headless runner — as you wish, if you want to test JS.