const options = {
  method: 'POST',
  headers: {
    Authorization: 'Bearer sk-ecozvmqvpaemrvsvwskgzbbluxnotuklyddgeawbikglhmdh',
    'Content-Type': 'application/json'
  },
  body: '{"model":"Qwen/Qwen2.5-VL-72B-Instruct","stream":false,"max_tokens":512,"enable_thinking":true,"thinking_budget":4096,"min_p":0.05,"temperature":0.7,"top_p":0.7,"top_k":50,"frequency_penalty":0.5,"n":1,"stop":[]}'
};

fetch('https://api.siliconflow.cn/v1/chat/completions', options)
  .then(response => response.json())
  .then(response => console.log(response))
  .catch(err => console.error(err));