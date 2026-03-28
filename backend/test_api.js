const axios = require('axios');

async function test() {
  try {
    const email = `testuser_${Date.now()}@example.com`;
    console.log("Registering user:", email);
    
    let res = await axios.post('http://localhost:8080/api/v1/auth/register', {
      name: "Test",
      lastname: "User",
      email: email,
      password: "password123",
      password_confirmation: "password123"
    });
    
    console.log("Register response HTTP:", res.status);
    console.log("Register data keys:", Object.keys(res.data.data));
    
    const token = res.data.data.token;
    
    console.log("Creating player profile...");
    let profRes = await axios.post('http://localhost:8080/api/v1/me/player-profile', {
      level: 4,
      nickname: "TestPlayer",
      dni: `1111${Math.floor(Math.random()*90000)}Y`
    }, {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });
    
    console.log("Profile response HTTP:", profRes.status);
    console.log("Profile data:", JSON.stringify(profRes.data, null, 2));

  } catch (err) {
    console.log("ERROR OCCURRED:");
    if (err.response) {
      console.log("Status:", err.response.status);
      console.log("Data:", JSON.stringify(err.response.data, null, 2));
    } else {
      console.log(err.message);
    }
  }
}

test();
